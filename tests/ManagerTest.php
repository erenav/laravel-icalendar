<?php

declare(strict_types=1);

namespace Erenav\LaravelICalendar\Tests;

use DateTimeImmutable;
use Erenav\ICalendar\Builder\CalendarBuilder;
use Erenav\ICalendar\Builder\EventBuilder;
use Erenav\ICalendar\Component\Event;
use Erenav\ICalendar\ValueType\DateTimeValue;
use Erenav\LaravelICalendar\Facades\ICalendar;
use Erenav\LaravelICalendar\ICalendarManager;

final class ManagerTest extends TestCase
{
    public function test_manager_is_bound_as_singleton(): void
    {
        $this->assertInstanceOf(ICalendarManager::class, app(ICalendarManager::class));
        $this->assertSame(app(ICalendarManager::class), app(ICalendarManager::class));
        $this->assertSame(app(ICalendarManager::class), app('icalendar'));
    }

    public function test_calendar_builder_uses_configured_product_id(): void
    {
        $this->assertInstanceOf(CalendarBuilder::class, ICalendar::calendar());
        $this->assertStringContainsString('PRODID:-//Test//EN', ICalendar::serialize(ICalendar::calendar()->get()));
    }

    public function test_event_returns_a_builder(): void
    {
        $this->assertInstanceOf(EventBuilder::class, ICalendar::event());
    }

    public function test_serialize_auto_includes_vtimezone(): void
    {
        $calendar = ICalendar::calendar()
            ->add(
                Event::build()->uid('1@test')
                    ->starts(DateTimeValue::zoned(new DateTimeImmutable('2026-07-01 09:30'), 'America/New_York')),
            )
            ->get();

        $ics = ICalendar::serialize($calendar);
        $this->assertStringContainsString('BEGIN:VTIMEZONE', $ics);
        $this->assertStringContainsString('TZID:America/New_York', $ics);
    }

    public function test_parse_round_trips(): void
    {
        $calendar = ICalendar::calendar()
            ->add(Event::build()->uid('1@test')->summary('Hi'))
            ->get();

        $parsed = ICalendar::parse(ICalendar::serialize($calendar));
        $this->assertSame('Hi', $parsed->events()[0]->summary());
    }
}
