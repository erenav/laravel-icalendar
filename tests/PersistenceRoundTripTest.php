<?php

declare(strict_types=1);

namespace Erenav\LaravelICalendar\Tests;

use Erenav\ICalendar\Component\Calendar;
use Erenav\ICalendar\Component\Component;
use Erenav\ICalendar\Component\Event;
use Erenav\ICalendar\Parameter\RawParameter;
use Erenav\ICalendar\Parser\Parser;
use Erenav\LaravelICalendar\Enums\EventComponentType;
use Erenav\LaravelICalendar\Importing\CalendarImporter;
use Erenav\LaravelICalendar\Models\CalendarEvent;
use Erenav\LaravelICalendar\Persistence\CalendarStore;
use Erenav\LaravelICalendar\Persistence\StoredCalendarExporter;

final class PersistenceRoundTripTest extends PersistenceTestCase
{
    private const ICS = <<<'ICS'
BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//Persistence Test//EN
NAME:External Calendar
X-CALENDAR-PROP;X-PARAM=kept:calendar value
BEGIN:VEVENT
UID:series@example.test
DTSTAMP:20260801T120000Z
SEQUENCE:2
DTSTART;TZID=America/New_York:20261101T013000
DTEND;TZID=America/New_York:20261101T023000
SUMMARY:Recurring meeting
RRULE:FREQ=WEEKLY;COUNT=3;X-ROTATION=A\;B
RDATE;TZID=America/New_York:20261108T013000
RDATE;VALUE=PERIOD:20261115T063000Z/20261115T073000Z
EXDATE;TZID=America/New_York:20261108T013000
EXDATE:20261122T063000Z
ORGANIZER;CN=Boss;SENT-BY="mailto:assistant@example.test";DIR="https://example.test/directory/boss";LANGUAGE=en;X-ORG=kept:mailto:boss@example.test
ATTENDEE;CN=Alice;ROLE=REQ-PARTICIPANT;PARTSTAT=ACCEPTED;RSVP=TRUE;MEMBER="mailto:team@example.test";DELEGATED-TO="mailto:bob@example.test";DELEGATED-FROM="mailto:carol@example.test";SENT-BY="mailto:assistant@example.test";DIR="https://example.test/directory/alice";LANGUAGE=en;X-ATT=kept:mailto:alice@example.test
X-EVENT-PROP;X-ONE=two:preserved
BEGIN:VALARM
ACTION:DISPLAY
TRIGGER:-PT15M
DESCRIPTION:Reminder
X-ALARM-PROP:preserved
END:VALARM
BEGIN:VALARM
ACTION:AUDIO
TRIGGER:-PT5M
X-SECOND-ALARM:preserved
END:VALARM
END:VEVENT
BEGIN:VEVENT
UID:series@example.test
DTSTAMP:20260802T120000Z
SEQUENCE:3
RECURRENCE-ID;RANGE=THISANDFUTURE;TZID=America/New_York:20261108T013000
DTSTART;TZID=America/New_York:20261108T033000
STATUS:CANCELLED
SUMMARY:Moved and cancelled tail
ATTENDEE;CN=Alice;PARTSTAT=DECLINED:mailto:alice@example.test
END:VEVENT
END:VCALENDAR
ICS;

    public function test_semantic_round_trip_preserves_recurrence_overrides_participants_alarms_and_unknown_data(): void
    {
        $calendar = app(CalendarStore::class)->create('Placeholder');
        $result = app(CalendarImporter::class)->importIcs($calendar, self::ICS);

        $this->assertSame(2, $result->counts()['created']);
        $this->assertSame('External Calendar', $calendar->refresh()->getAttribute('name'));

        $master = CalendarEvent::query()->where('component_type', EventComponentType::RecurringMaster)->firstOrFail();
        $override = CalendarEvent::query()->where('component_type', EventComponentType::DetachedOverride)->firstOrFail();
        $this->assertSame($master->getKey(), $override->getAttribute('recurring_master_id'));
        $this->assertSame('THISANDFUTURE', $override->getAttribute('recurrence_range'));
        $this->assertSame('zoned', $master->getAttribute('dtstart_type')->value);
        $this->assertCount(2, $master->participants);
        $this->assertCount(2, $master->alarms);
        $attendee = $master->participants()->where('type', 'attendee')->firstOrFail();
        $this->assertSame(['mailto:carol@example.test'], $attendee->getAttribute('delegated_from'));
        $this->assertSame('mailto:assistant@example.test', $attendee->getAttribute('sent_by'));
        $this->assertSame('https://example.test/directory/alice', $attendee->getAttribute('directory'));
        $this->assertSame('en', $attendee->getAttribute('language'));

        $exported = app(StoredCalendarExporter::class)->calendarIcs($calendar);
        $parsed = Parser::lenient()->parseCalendar($exported);
        $this->assertCount(2, $parsed->events());
        $this->assertSame('A\;B', $parsed->events()[0]->recurrenceRule()?->unknownParts[0]->value);
        $this->assertCount(1, $parsed->events()[0]->recurrenceDatePeriods());
        $this->assertCount(2, $parsed->events()[0]->exceptionDates());
        $this->assertSame('two', $parsed->events()[0]->property('X-EVENT-PROP')?->parameter('X-ONE')?->value());
        $this->assertSame('preserved', $parsed->events()[0]->alarms()[0]->property('X-ALARM-PROP')?->value()->toString());
        $this->assertSame('THISANDFUTURE', $parsed->events()[1]->recurrenceRange()?->value);
    }

    public function test_itip_method_and_participation_data_survive_persistence(): void
    {
        $source = Calendar::build()
            ->prodId('-//Scheduling Test//EN')
            ->method('REQUEST')
            ->add(
                Event::build()
                    ->uid('invite@example.test')
                    ->sequence(2)
                    ->organizer('organizer@example.test')
                    ->addAttendee('attendee@example.test', rsvp: true),
            )
            ->get();

        $stored = app(CalendarStore::class)->createFromCore($source);
        $reloaded = $stored::query()->whereKey($stored->getKey())->firstOrFail();
        $exported = app(StoredCalendarExporter::class)->calendar($reloaded);

        $this->assertSame('REQUEST', $exported->method());
        $this->assertSame('mailto:organizer@example.test', $exported->events()[0]->organizer()?->address()->toString());
        $this->assertTrue($exported->events()[0]->attendees()[0]->rsvp());
    }

    public function test_itip_request_reply_and_cancellation_metadata_survive_fresh_database_round_trips(): void
    {
        foreach ([
            'REQUEST' => ['ACCEPTED', 'CONFIRMED'],
            'REPLY' => ['DECLINED', 'CONFIRMED'],
            'CANCEL' => ['DECLINED', 'CANCELLED'],
        ] as $method => [$partStat, $status]) {
            $source = Parser::lenient()->parseCalendar(<<<ICS
BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//iTIP persistence//EN
METHOD:{$method}
BEGIN:VEVENT
UID:{$method}@example.test
DTSTAMP:20260815T120000Z
SEQUENCE:4
DTSTART:20260820T130000Z
STATUS:{$status}
ORGANIZER;CN=Organizer;SENT-BY="mailto:assistant@example.test":mailto:organizer@example.test
ATTENDEE;CN=Attendee;PARTSTAT={$partStat};RSVP=TRUE;DELEGATED-FROM="mailto:delegate@example.test":mailto:attendee@example.test
END:VEVENT
END:VCALENDAR
ICS);

            $stored = app(CalendarStore::class)->createFromCore($source);
            $fresh = $stored::query()->whereKey($stored->getKey())->firstOrFail();
            $reconstructed = app(StoredCalendarExporter::class)->calendar($fresh);
            $reparsed = Parser::lenient()->parseCalendar(
                app(StoredCalendarExporter::class)->calendarIcs($fresh),
            );

            $this->assertSame($this->fingerprint($source), $this->fingerprint($reconstructed), $method);
            $this->assertSame($this->fingerprint($source), $this->fingerprint($reparsed), $method);
            $this->assertSame($method, $reparsed->method());
            $this->assertSame($partStat, $reparsed->events()[0]->attendees()[0]->participationStatus()?->value);
            $this->assertSame($status === 'CANCELLED', $reparsed->events()[0]->isCancelled());
        }
    }

    public function test_recurring_masters_and_overrides_link_in_either_document_order(): void
    {
        $master = <<<'ICS'
BEGIN:VEVENT
UID:document-order
DTSTART:20260815T090000Z
RRULE:FREQ=DAILY;COUNT=2
END:VEVENT
ICS;
        $override = <<<'ICS'
BEGIN:VEVENT
UID:document-order
RECURRENCE-ID:20260816T090000Z
DTSTART:20260816T100000Z
SUMMARY:Moved
END:VEVENT
ICS;

        foreach ([[$master, $override], [$override, $master]] as $components) {
            $source = Parser::lenient()->parseCalendar(
                "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//Document order//EN\r\n".
                str_replace("\n", "\r\n", implode("\n", $components)).
                "\r\nEND:VCALENDAR\r\n",
            );
            $stored = app(CalendarStore::class)->createFromCore($source);
            $fresh = $stored::query()->whereKey($stored->getKey())->firstOrFail();
            $rows = $fresh->events()->get()->keyBy(fn ($event): string => $event->getAttribute('component_type')->value);

            $this->assertSame(
                $rows['recurring_master']->getKey(),
                $rows['detached_override']->getAttribute('recurring_master_id'),
            );
            $this->assertSame(
                $this->fingerprint($source),
                $this->fingerprint(app(StoredCalendarExporter::class)->calendar($fresh)),
            );
        }
    }

    public function test_embedded_custom_timezone_and_unknown_calendar_component_survive(): void
    {
        $ics = <<<'ICS'
BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//Custom Zone//EN
BEGIN:VTIMEZONE
TZID:X-OFFICE
BEGIN:STANDARD
DTSTART:19700101T000000
TZOFFSETFROM:-0500
TZOFFSETTO:-0500
TZNAME:Office
END:STANDARD
END:VTIMEZONE
BEGIN:X-CALENDAR-DATA
X-UNKNOWN:preserved
END:X-CALENDAR-DATA
BEGIN:VEVENT
UID:custom-zone
DTSTART;TZID=X-OFFICE:20260815T090000
SUMMARY:Custom zone
END:VEVENT
END:VCALENDAR
ICS;
        $calendar = app(CalendarStore::class)->create('Custom');
        app(CalendarImporter::class)->importIcs($calendar, $ics);
        $event = CalendarEvent::query()->firstOrFail();

        $this->assertSame('X-OFFICE', $event->getAttribute('dtstart_timezone'));
        $this->assertNotNull($event->getAttribute('dtstart_utc'));
        app(CalendarStore::class)->replaceEvent($event, Parser::lenient()->parseCalendar($ics)->events()[0]);
        $this->assertNotNull($event->refresh()->getAttribute('dtstart_utc'));
        $exported = Parser::lenient()->parseCalendar(app(StoredCalendarExporter::class)->calendarIcs($calendar));
        $this->assertSame('X-OFFICE', $exported->timeZones()[0]->tzid());
        $this->assertSame('X-CALENDAR-DATA', $exported->components()[1]->wireName());
        $this->assertSame('preserved', $exported->components()[1]->property('X-UNKNOWN')?->value()->toString());
    }

    public function test_custom_timezone_identity_stays_stable_when_resolution_becomes_available_or_changes(): void
    {
        $withoutDefinition = <<<'ICS'
BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//Custom Identity//EN
BEGIN:VEVENT
UID:custom-identity
RECURRENCE-ID;TZID=X-OFFICE:20260815T090000
DTSTART;TZID=X-OFFICE:20260815T100000
SEQUENCE:1
END:VEVENT
END:VCALENDAR
ICS;
        $withOffset = static fn (string $offset): string => <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//Custom Identity//EN
BEGIN:VTIMEZONE
TZID:X-OFFICE
BEGIN:STANDARD
DTSTART:19700101T000000
TZOFFSETFROM:{$offset}
TZOFFSETTO:{$offset}
END:STANDARD
END:VTIMEZONE
BEGIN:VEVENT
UID:custom-identity
RECURRENCE-ID;TZID=X-OFFICE:20260815T090000
DTSTART;TZID=X-OFFICE:20260815T100000
SEQUENCE:1
END:VEVENT
END:VCALENDAR
ICS;

        $calendar = app(CalendarStore::class)->create('Custom identity');
        $initial = app(CalendarImporter::class)->importIcs($calendar, $withoutDefinition);
        $record = CalendarEvent::query()->firstOrFail();
        $unresolvedHash = $record->getAttribute('identity_hash');
        $this->assertSame(1, $initial->counts()['created']);
        $this->assertNull($record->getAttribute('dtstart_utc'));

        $resolved = app(CalendarImporter::class)->importIcs($calendar, $withOffset('-0500'));
        $freshResolved = CalendarEvent::query()->firstOrFail();
        $resolvedHash = $freshResolved->getAttribute('identity_hash');
        $this->assertSame(1, $resolved->counts()['unchanged']);
        $this->assertSame(1, CalendarEvent::query()->count());
        $this->assertNotSame($unresolvedHash, $resolvedHash);
        $this->assertSame('2026-08-15 15:00:00', $freshResolved->getAttribute('dtstart_utc')?->format('Y-m-d H:i:s'));

        $changedDefinition = app(CalendarImporter::class)->importIcs($calendar, $withOffset('-0400'));
        $freshChanged = CalendarEvent::query()->firstOrFail();
        $this->assertSame(1, $changedDefinition->counts()['unchanged']);
        $this->assertSame(1, CalendarEvent::query()->count());
        $this->assertNotSame($resolvedHash, $freshChanged->getAttribute('identity_hash'));
        $this->assertSame('2026-08-15 14:00:00', $freshChanged->getAttribute('dtstart_utc')?->format('Y-m-d H:i:s'));
    }

    public function test_repeated_import_is_idempotent_and_stale_revision_is_rejected(): void
    {
        $calendar = app(CalendarStore::class)->create('Imports');
        $first = app(CalendarImporter::class)->importIcs($calendar, self::ICS);
        $second = app(CalendarImporter::class)->importIcs($calendar, self::ICS);

        $this->assertSame(2, $first->counts()['created']);
        $this->assertSame(2, $second->counts()['unchanged']);
        $this->assertSame(2, CalendarEvent::query()->count());

        $stale = str_replace(['SEQUENCE:2', 'Recurring meeting'], ['SEQUENCE:1', 'Stale meeting'], self::ICS);
        $third = app(CalendarImporter::class)->importIcs($calendar, $stale);
        $this->assertGreaterThanOrEqual(1, $third->counts()['skipped']);
        $this->assertSame('Recurring meeting', CalendarEvent::query()->whereNull('recurrence_id_value')->firstOrFail()->getAttribute('summary'));
    }

    public function test_freshly_reloaded_calendar_has_the_same_recursive_semantics(): void
    {
        $ics = <<<'ICS'
BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//Semantic Audit//EN
X-CALENDAR;X-CUSTOM=calendar:kept
BEGIN:VTIMEZONE
TZID:X-OFFICE
BEGIN:STANDARD
DTSTART:19700101T000000
TZOFFSETFROM:-0500
TZOFFSETTO:-0500
END:STANDARD
END:VTIMEZONE
BEGIN:VEVENT
UID:all-day
DTSTART;VALUE=DATE:20260815
X-REPEATED;X-ID=one:first
X-REPEATED;X-ID=two:second
END:VEVENT
BEGIN:X-BETWEEN
X-CHILD;X-PARAM=kept:value
END:X-BETWEEN
BEGIN:VEVENT
UID:floating
DTSTART:20260815T090000
DURATION:PT1H
END:VEVENT
BEGIN:VEVENT
UID:utc
DTSTART:20260815T130000Z
DTEND:20260815T140000Z
END:VEVENT
BEGIN:VEVENT
UID:zoned
DTSTART;TZID=America/New_York:20261101T013000
ORGANIZER;CN=Boss;X-ORG=kept:mailto:boss@example.test
ATTENDEE;CN=Alice;PARTSTAT=ACCEPTED;RSVP=TRUE;DELEGATED-TO="mailto:bob@example.test";DELEGATED-FROM="mailto:carol@example.test";X-ATT=kept:mailto:alice@example.test
BEGIN:VALARM
ACTION:DISPLAY
TRIGGER:-PT15M
DESCRIPTION:Reminder
X-ALARM:kept
END:VALARM
END:VEVENT
BEGIN:VEVENT
UID:custom-zone
DTSTART;TZID=X-OFFICE:20260816T090000
END:VEVENT
BEGIN:VEVENT
UID:series
DTSTART;TZID=America/New_York:20261101T013000
DURATION:PT1H
RRULE:FREQ=WEEKLY;COUNT=5;X-ROTATION=A\;B
RDATE;TZID=America/New_York:20261108T013000
RDATE:20261115T063000Z
RDATE;VALUE=PERIOD:20261122T063000Z/20261122T073000Z
EXDATE;TZID=America/New_York:20261108T013000
EXDATE:20261129T063000Z
END:VEVENT
BEGIN:VEVENT
UID:series
RECURRENCE-ID;TZID=America/New_York:20261108T013000
DTSTART;TZID=America/New_York:20261108T033000
SUMMARY:Moved
END:VEVENT
BEGIN:VEVENT
UID:series
RECURRENCE-ID;TZID=America/New_York:20261115T013000
SUMMARY:Sparse modified
END:VEVENT
BEGIN:VEVENT
UID:series
RECURRENCE-ID;TZID=America/New_York:20261122T013000
STATUS:CANCELLED
END:VEVENT
BEGIN:VEVENT
UID:series
RECURRENCE-ID;RANGE=THISANDFUTURE;TZID=America/New_York:20261129T013000
DTSTART;TZID=America/New_York:20261129T023000
END:VEVENT
END:VCALENDAR
ICS;
        $source = Parser::lenient()->parseCalendar($ics);
        $stored = app(CalendarStore::class)->createFromCore($source);

        $freshCalendar = $stored::query()->whereKey($stored->getKey())->firstOrFail();
        $reconstructed = app(StoredCalendarExporter::class)->calendar($freshCalendar);
        $reparsed = Parser::lenient()->parseCalendar(app(StoredCalendarExporter::class)->calendarIcs($freshCalendar));

        $this->assertSame($this->fingerprint($source), $this->fingerprint($reconstructed));
        $this->assertSame($this->fingerprint($source), $this->fingerprint($reparsed));
        $this->assertSame(
            ['VTIMEZONE', 'VEVENT', 'X-BETWEEN', 'VEVENT', 'VEVENT', 'VEVENT', 'VEVENT', 'VEVENT', 'VEVENT', 'VEVENT', 'VEVENT', 'VEVENT'],
            array_map(static fn (Component $component): string => $component->wireName(), $reparsed->components()),
        );
    }

    /** @return array<string, mixed> */
    private function fingerprint(Component $component): array
    {
        return [
            'name' => $component->wireName(),
            'properties' => array_map(static function ($property): array {
                $parameters = [];
                foreach ($property->parameters as $parameter) {
                    $parameters[] = $parameter instanceof RawParameter
                        ? [$parameter->name, $parameter->values]
                        : [$parameter->parameterName(), [$parameter->token()]];
                }

                return [
                    $property->name,
                    array_map(static fn ($value): array => [$value::class, $value->toString()], $property->values),
                    $parameters,
                ];
            }, $component->properties->all()),
            'children' => array_map($this->fingerprint(...), $component->children->all()),
        ];
    }
}
