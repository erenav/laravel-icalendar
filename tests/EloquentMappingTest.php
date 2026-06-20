<?php

declare(strict_types=1);

namespace Vanere\LaravelICalendar\Tests;

use Illuminate\Database\Eloquent\Model;
use Vanere\ICalendar\Component\Event;
use Vanere\LaravelICalendar\Concerns\InteractsWithCalendar;
use Vanere\LaravelICalendar\Contracts\ProvidesCalendarEvent;
use Vanere\LaravelICalendar\Facades\ICalendar;

final class FakeMeeting extends Model implements ProvidesCalendarEvent
{
    use InteractsWithCalendar;

    protected $guarded = [];

    protected $casts = ['starts_at' => 'datetime'];

    public function toCalendarEvent(): Event
    {
        return Event::build()
            ->uid($this->calendarUid())
            ->summary($this->title)
            ->starts($this->starts_at)
            ->get();
    }
}

final class EloquentMappingTest extends TestCase
{
    private function meeting(): FakeMeeting
    {
        return new FakeMeeting(['id' => 42, 'title' => 'Team Sync', 'starts_at' => '2026-07-01 10:00:00']);
    }

    public function test_model_maps_to_event_with_deterministic_uid(): void
    {
        $event = $this->meeting()->toCalendarEvent();

        $this->assertSame('Team Sync', $event->summary());
        $this->assertSame('FakeMeeting-42@example.test', $event->uid());
    }

    public function test_to_ics_serializes_single_event_calendar(): void
    {
        $ics = $this->meeting()->toIcs();

        $this->assertStringContainsString('BEGIN:VEVENT', $ics);
        $this->assertStringContainsString('SUMMARY:Team Sync', $ics);
        $this->assertStringContainsString('PRODID:-//Test//EN', $ics);
    }

    public function test_from_models_builds_a_calendar(): void
    {
        $calendar = ICalendar::fromModels([
            $this->meeting(),
            new FakeMeeting(['id' => 43, 'title' => 'Retro', 'starts_at' => '2026-07-02 10:00:00']),
        ]);

        $this->assertCount(2, $calendar->events());
        $this->assertSame('Team Sync', $calendar->events()[0]->summary());
        $this->assertSame('Retro', $calendar->events()[1]->summary());
    }
}
