<?php

declare(strict_types=1);

namespace Erenav\LaravelICalendar\Persistence;

use Erenav\ICalendar\Component\Calendar as CoreCalendar;
use Erenav\ICalendar\Component\ComponentList;
use Erenav\ICalendar\Component\Event;
use Erenav\ICalendar\TimeZone\TimeZoneResolver;
use Erenav\LaravelICalendar\Support\ModelRegistry;
use Erenav\LaravelICalendar\Support\ModelValue;
use Illuminate\Database\Eloquent\Model;

final class StoredCalendarExporter
{
    public function __construct(
        private readonly CoreComponentCodec $codec,
        private readonly PersistenceGuard $guard,
    ) {}

    public function event(Model $record): Event
    {
        $this->guard->ensureEnabled();

        return $this->codec->decodeEvent(ModelValue::string($record, 'component_ics'));
    }

    public function eventIcs(Model $record): string
    {
        return $this->codec->encode($this->eventCalendar($record));
    }

    /** Return the canonical stored VEVENT component without a VCALENDAR wrapper. */
    public function eventComponentIcs(Model $record): string
    {
        return $this->codec->encode($this->event($record));
    }

    public function eventCalendar(Model $record): CoreCalendar
    {
        $this->guard->ensureEnabled();
        $calendarClass = ModelRegistry::calendar();
        $calendar = $calendarClass::query()->whereKey($record->getAttribute('calendar_id'))->firstOrFail();
        $envelope = $this->codec->decodeCalendarEnvelope(
            is_string($calendar->getAttribute('component_ics')) ? $calendar->getAttribute('component_ics') : null,
        );

        $nonEvents = array_values(array_filter(
            $envelope->components(),
            static fn ($component): bool => ! $component instanceof Event,
        ));

        return new CoreCalendar(
            $envelope->properties,
            new ComponentList(...[...$nonEvents, $this->event($record)]),
        );
    }

    public function calendar(Model $record): CoreCalendar
    {
        $this->guard->ensureEnabled();
        $envelope = $this->codec->decodeCalendarEnvelope(
            is_string($record->getAttribute('component_ics')) ? $record->getAttribute('component_ics') : null,
        );
        $eventClass = ModelRegistry::event();
        $events = $eventClass::query()
            ->where('calendar_id', $record->getKey())
            ->orderBy('created_at')
            ->orderBy('id')
            ->get()
            ->map(fn (Model $event): Event => $this->event($event))
            ->all();
        $timeZones = TimeZoneResolver::fromCalendar($envelope);

        /** @var array<string, list<Event>> $byIdentity */
        $byIdentity = [];
        foreach ($events as $event) {
            $byIdentity[EventIdentity::key($event, $timeZones)][] = $event;
        }

        $components = [];
        foreach ($envelope->components() as $component) {
            if (! $component instanceof Event) {
                $components[] = $component;

                continue;
            }

            $key = EventIdentity::key($component, $timeZones);
            if (($byIdentity[$key] ?? []) !== []) {
                $components[] = array_shift($byIdentity[$key]);
            }
        }
        foreach ($events as $event) {
            $key = EventIdentity::key($event, $timeZones);
            if (($byIdentity[$key] ?? []) !== [] && $byIdentity[$key][0] === $event) {
                $components[] = array_shift($byIdentity[$key]);
            }
        }

        return new CoreCalendar(
            $envelope->properties,
            new ComponentList(...$components),
        );
    }

    public function calendarIcs(Model $record): string
    {
        return $this->codec->encode($this->calendar($record));
    }
}
