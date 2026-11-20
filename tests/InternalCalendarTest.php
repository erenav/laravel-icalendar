<?php

declare(strict_types=1);

namespace Erenav\LaravelICalendar\Tests;

use Erenav\ICalendar\Component\Alarm;
use Erenav\ICalendar\Component\Event;
use Erenav\ICalendar\Parameter\Range;
use Erenav\ICalendar\Parser\Parser;
use Erenav\ICalendar\Property\AlarmAction;
use Erenav\ICalendar\Recurrence\Recurrence;
use Erenav\ICalendar\ValueType\DateTimeValue;
use Erenav\ICalendar\ValueType\Duration;
use Erenav\LaravelICalendar\Enums\EventComponentType;
use Erenav\LaravelICalendar\Models\CalendarEvent;
use Erenav\LaravelICalendar\Models\EventAlarm;
use Erenav\LaravelICalendar\Models\EventParticipant;
use Erenav\LaravelICalendar\Persistence\CalendarStore;
use Erenav\LaravelICalendar\Persistence\StoredCalendarExporter;

final class InternalCalendarTest extends PersistenceTestCase
{
    public function test_persisted_events_require_one_unambiguous_uid_and_recurrence_id(): void
    {
        $calendar = app(CalendarStore::class)->create('Identity validation');

        try {
            app(CalendarStore::class)->putEvent($calendar, Event::build()->summary('No UID')->get());
            $this->fail('A persisted VEVENT must have a UID.');
        } catch (\InvalidArgumentException $exception) {
            $this->assertStringContainsString('UID', $exception->getMessage());
        }

        $ambiguous = Parser::lenient()->parse(<<<'ICS'
BEGIN:VEVENT
UID:first
UID:second
RECURRENCE-ID:20260815T090000Z
RECURRENCE-ID:20260816T090000Z
END:VEVENT
ICS);
        $this->assertInstanceOf(Event::class, $ambiguous);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('UID');
        app(CalendarStore::class)->putEvent($calendar, $ambiguous);
    }

    public function test_internal_recurring_series_and_detached_overrides_are_managed_without_occurrence_rows(): void
    {
        $start = DateTimeValue::utc(new \DateTimeImmutable('2026-08-15 09:00:00 UTC'));
        $overrideId = DateTimeValue::utc(new \DateTimeImmutable('2026-08-16 09:00:00 UTC'));
        $master = Event::build()->uid('internal-series')->starts($start)->recurrence(Recurrence::daily()->times(10))->get();
        $override = Event::build()->uid('internal-series')->recurrenceId($overrideId, Range::ThisAndFuture)
            ->starts(DateTimeValue::utc(new \DateTimeImmutable('2026-08-16 10:00:00 UTC')))->get();
        $calendar = app(CalendarStore::class)->create('Internal');

        $records = app(CalendarStore::class)->upsertRecurringSeries($calendar, $master, [$override]);

        $this->assertCount(2, $records);
        $this->assertSame(EventComponentType::RecurringMaster, $records[0]->getAttribute('component_type'));
        $this->assertSame(EventComponentType::DetachedOverride, $records[1]->getAttribute('component_type'));
        $this->assertSame($records[0]->getKey(), $records[1]->getAttribute('recurring_master_id'));
        $this->assertSame(2, $calendar->events()->count());
    }

    public function test_replacing_a_series_master_with_a_standalone_event_clears_stale_links(): void
    {
        $start = DateTimeValue::utc(new \DateTimeImmutable('2026-08-15 09:00:00 UTC'));
        $slot = DateTimeValue::utc(new \DateTimeImmutable('2026-08-16 09:00:00 UTC'));
        $calendar = app(CalendarStore::class)->create('Relink');
        [$master, $override] = app(CalendarStore::class)->upsertRecurringSeries(
            $calendar,
            Event::build()->uid('old-series')->starts($start)->recurrence(Recurrence::daily()->times(2))->get(),
            [Event::build()->uid('old-series')->recurrenceId($slot)->summary('Override')->get()],
        );
        $this->assertSame($master->getKey(), $override->getAttribute('recurring_master_id'));

        app(CalendarStore::class)->replaceEvent($master, Event::build()->uid('now-standalone')->summary('Standalone')->get());

        $this->assertNull($master->refresh()->getAttribute('recurring_master_id'));
        $this->assertNull($override->refresh()->getAttribute('recurring_master_id'));
        $this->assertSame(EventComponentType::Standalone, $master->getAttribute('component_type'));
    }

    public function test_moving_a_series_master_to_another_calendar_moves_its_overrides(): void
    {
        $start = DateTimeValue::utc(new \DateTimeImmutable('2026-08-15 09:00:00 UTC'));
        $slot = DateTimeValue::utc(new \DateTimeImmutable('2026-08-16 09:00:00 UTC'));
        $from = app(CalendarStore::class)->create('From');
        $to = app(CalendarStore::class)->create('To');
        $master = Event::build()->uid('moving-series')->starts($start)->recurrence(Recurrence::daily()->times(3))->get();
        [$masterRecord, $override] = app(CalendarStore::class)->upsertRecurringSeries(
            $from,
            $master,
            [Event::build()->uid('moving-series')->recurrenceId($slot)
                ->starts(DateTimeValue::utc(new \DateTimeImmutable('2026-08-16 11:00:00 UTC')))->summary('Moved')->get()],
        );

        app(CalendarStore::class)->replaceEvent($masterRecord, $master, $to->getKey());

        $override->refresh();
        $this->assertSame(0, $from->events()->count());
        $this->assertSame(2, $to->events()->count());
        $this->assertSame((string) $to->getKey(), (string) $override->getAttribute('calendar_id'));
        $this->assertSame($masterRecord->getKey(), $override->getAttribute('recurring_master_id'));
        $occurrences = app(StoredCalendarExporter::class)->calendar($to->refresh())->occurrencesBetween(
            new \DateTimeImmutable('2026-08-16 00:00:00 UTC'),
            new \DateTimeImmutable('2026-08-16 23:59:59 UTC'),
        );
        $this->assertCount(1, $occurrences);
        $this->assertSame('Moved', $occurrences[0]->event->summary());
    }

    public function test_moving_a_master_under_a_new_uid_leaves_the_old_series_overrides(): void
    {
        $start = DateTimeValue::utc(new \DateTimeImmutable('2026-08-15 09:00:00 UTC'));
        $slot = DateTimeValue::utc(new \DateTimeImmutable('2026-08-16 09:00:00 UTC'));
        $from = app(CalendarStore::class)->create('From');
        $to = app(CalendarStore::class)->create('To');
        [$masterRecord, $override] = app(CalendarStore::class)->upsertRecurringSeries(
            $from,
            Event::build()->uid('old-uid')->starts($start)->recurrence(Recurrence::daily()->times(2))->get(),
            [Event::build()->uid('old-uid')->recurrenceId($slot)->summary('Override')->get()],
        );

        app(CalendarStore::class)->replaceEvent($masterRecord, Event::build()->uid('new-uid')->starts($start)->get(), $to->getKey());

        $this->assertSame((string) $from->getKey(), (string) $override->refresh()->getAttribute('calendar_id'));
        $this->assertNull($override->getAttribute('recurring_master_id'));
    }

    public function test_application_deletion_uses_foreign_key_cascades_and_nulls_override_links(): void
    {
        $start = DateTimeValue::utc(new \DateTimeImmutable('2026-08-15 09:00:00 UTC'));
        $slot = DateTimeValue::utc(new \DateTimeImmutable('2026-08-16 09:00:00 UTC'));
        $calendar = app(CalendarStore::class)->create('Deletion');
        [$master, $override] = app(CalendarStore::class)->upsertRecurringSeries(
            $calendar,
            Event::build()->uid('delete-series')->starts($start)->recurrence(Recurrence::daily()->times(2))
                ->addAttendee('person@example.test')
                ->addAlarm(Alarm::build()->action(AlarmAction::Display)->trigger(Duration::minutes(-5)))
                ->get(),
            [Event::build()->uid('delete-series')->recurrenceId($slot)->get()],
        );

        $master->delete();

        $this->assertNull($override->refresh()->getAttribute('recurring_master_id'));
        $this->assertSame(0, EventParticipant::query()->count());
        $this->assertSame(0, EventAlarm::query()->count());

        $calendar->delete();

        $this->assertSame(0, CalendarEvent::query()->count());
    }
}
