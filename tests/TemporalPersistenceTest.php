<?php

declare(strict_types=1);

namespace Erenav\LaravelICalendar\Tests;

use Erenav\ICalendar\Component\Calendar;
use Erenav\ICalendar\Component\Event;
use Erenav\ICalendar\ValueType\DateTimeValue;
use Erenav\ICalendar\ValueType\Duration;
use Erenav\LaravelICalendar\Persistence\CalendarStore;
use Erenav\LaravelICalendar\Persistence\StoredCalendarExporter;

final class TemporalPersistenceTest extends PersistenceTestCase
{
    public function test_date_floating_utc_zoned_dtend_duration_and_absent_end_remain_distinct(): void
    {
        $core = Calendar::build()
            ->prodId('-//Temporal Test//EN')
            ->add(Event::build()->uid('date')->starts(DateTimeValue::date(new \DateTimeImmutable('2026-08-15'))))
            ->add(Event::build()->uid('floating')->starts(DateTimeValue::floating(new \DateTimeImmutable('2026-08-15 09:00')))->lasting(Duration::hours(1)))
            ->add(Event::build()->uid('utc')->starts(DateTimeValue::utc(new \DateTimeImmutable('2026-08-15 13:00 UTC')))->ends(DateTimeValue::utc(new \DateTimeImmutable('2026-08-15 14:00 UTC'))))
            ->add(Event::build()->uid('zoned')->starts(DateTimeValue::zoned(new \DateTimeImmutable('2026-11-01 01:30'), 'America/New_York')))
            ->get();
        $calendar = app(CalendarStore::class)->create('Temporal');
        app(CalendarStore::class)->import($calendar, $core);

        $rows = $calendar->events()->get()->keyBy('uid');
        $this->assertSame('date', $rows['date']->getAttribute('dtstart_type')->value);
        $this->assertNull($rows['date']->getAttribute('dtstart_utc'));
        $this->assertSame('floating', $rows['floating']->getAttribute('dtstart_type')->value);
        $this->assertSame('PT1H', $rows['floating']->getAttribute('duration'));
        $this->assertNull($rows['floating']->getAttribute('dtend_value'));
        $this->assertSame('utc', $rows['utc']->getAttribute('dtstart_type')->value);
        $this->assertNotNull($rows['utc']->getAttribute('dtend_utc'));
        $this->assertSame('zoned', $rows['zoned']->getAttribute('dtstart_type')->value);
        $this->assertSame('America/New_York', $rows['zoned']->getAttribute('dtstart_timezone'));
        $this->assertNull($rows['zoned']->getAttribute('dtend_value'));
        $this->assertNull($rows['zoned']->getAttribute('duration'));

        $exported = app(StoredCalendarExporter::class)->calendar($calendar);
        $this->assertSame(['date', 'floating', 'utc', 'zoned'], array_map(static fn (Event $event): ?string => $event->uid(), $exported->events()));
    }
}
