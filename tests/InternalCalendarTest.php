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
