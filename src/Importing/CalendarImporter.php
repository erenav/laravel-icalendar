<?php

declare(strict_types=1);

namespace Erenav\LaravelICalendar\Importing;

use Erenav\ICalendar\Component\Calendar as CoreCalendar;
use Erenav\ICalendar\Component\Event;
use Erenav\ICalendar\Parser\Parser;
use Erenav\ICalendar\Recurrence\EventRevisionComparator;
use Erenav\ICalendar\TimeZone\TimeZoneResolver;
use Erenav\LaravelICalendar\Persistence\CoreComponentCodec;
use Erenav\LaravelICalendar\Persistence\CoreEventMapper;
use Erenav\LaravelICalendar\Persistence\EventIdentity;
use Erenav\LaravelICalendar\Persistence\PersistenceGuard;
use Erenav\LaravelICalendar\Support\ModelRegistry;
use Erenav\LaravelICalendar\Support\ModelValue;
use Illuminate\Database\Eloquent\Model;
use Throwable;

final class CalendarImporter
{
    public function __construct(
        private readonly CoreComponentCodec $codec,
        private readonly CoreEventMapper $events,
        private readonly EventRevisionComparator $revisions,
        private readonly PersistenceGuard $guard,
    ) {}

    public function importIcs(Model $calendar, string $ics): ImportResult
    {
        $this->guard->ensureEnabled();

        return $this->importCalendar($calendar, Parser::lenient()->parseCalendar($ics));
    }

    public function previewIcs(string $ics): ImportPreview
    {
        return $this->previewCalendar(Parser::lenient()->parseCalendar($ics));
    }

    public function previewCalendar(CoreCalendar $calendar): ImportPreview
    {
        [$selected, $discarded, $invalid] = $this->selectRevisions(
            $calendar->events(),
            TimeZoneResolver::fromCalendar($calendar),
        );

        return new ImportPreview($calendar, $selected, $discarded, $invalid);
    }

    public function importCalendar(Model $calendar, CoreCalendar $source): ImportResult
    {
        $this->guard->ensureEnabled();
        $result = new ImportResult;
        $preview = $this->previewCalendar($source);
        $result->skipped = $preview->discardedRevisionIds;
        $result->invalid = $preview->invalid;

        if ($result->invalid !== []) {
            return $result;
        }

        $calendar->getConnection()->transaction(function () use ($calendar, $source, $preview, $result): void {
            $calendar->setAttribute('component_ics', $this->codec->encodeCalendarEnvelope($source));
            if (($name = $source->name()) !== null) {
                $calendar->setAttribute('name', $name);
            }
            $calendar->setAttribute('description', $source->property('X-WR-CALDESC')?->value()->toString());
            $calendar->setAttribute('timezone', $source->property('X-WR-TIMEZONE')?->value()->toString());
            $calendar->setAttribute('color', $source->property('COLOR')?->value()->toString());
            $calendar->save();

            $timeZones = TimeZoneResolver::fromCalendar($source);
            foreach ($preview->selectedEvents as $event) {
                $this->upsert($calendar, $event, $result, $timeZones);
            }

            $this->linkOverrides($calendar);
        });

        return $result;
    }

    private function upsert(
        Model $calendar,
        Event $incoming,
        ImportResult $result,
        TimeZoneResolver $timeZones,
    ): void {
        EventIdentity::assertValid($incoming);
        $this->revisions->compare($incoming, $incoming);

        $record = $this->match($calendar, $incoming, $timeZones);
        if ($record !== null) {
            $comparison = $this->revisions->compare($incoming, $this->events->toCore($record));

            if ($comparison < 0) {
                $result->skipped[] = ModelValue::key($record);

                return;
            }

            if ($comparison === 0) {
                $this->events->fill($record, ModelValue::key($calendar), $incoming, $timeZones);
                $record->save();
                $result->unchanged[] = ModelValue::key($record);

                return;
            }
        }

        $eventClass = ModelRegistry::event();
        $record ??= new $eventClass;
        $created = ! $record->exists;
        $this->events->fill($record, ModelValue::key($calendar), $incoming, $timeZones);
        $record->save();
        $this->events->reconcileChildren($record, $incoming);

        if ($created) {
            $result->created[] = ModelValue::key($record);
        } else {
            $result->updated[] = ModelValue::key($record);
        }
    }

    private function match(Model $calendar, Event $incoming, TimeZoneResolver $timeZones): ?Model
    {
        $eventClass = ModelRegistry::event();
        $matched = $eventClass::query()
            ->where('calendar_id', $calendar->getKey())
            ->where('identity_hash', EventIdentity::hash($incoming, $timeZones))
            ->first();

        if ($matched !== null) {
            return $matched;
        }

        $candidates = $eventClass::query()
            ->where('calendar_id', $calendar->getKey())
            ->where('uid_hash', hash('sha256', $incoming->uid() ?? ''))
            ->get();
        foreach ($candidates as $candidate) {
            if (EventIdentity::matches($this->events->toCore($candidate), $incoming, $timeZones)) {
                return $candidate;
            }
        }

        return null;
    }

    private function linkOverrides(Model $calendar): void
    {
        $class = ModelRegistry::event();
        $events = $class::query()->where('calendar_id', $calendar->getKey())->get();
        $masters = [];
        foreach ($events as $event) {
            $core = $this->events->toCore($event);
            if ($core->recurrenceId() === null && $core->isRecurring()) {
                $masters[$core->uid() ?? ''] = $event->getKey();
            }
        }
        foreach ($events as $event) {
            $core = $this->events->toCore($event);
            $masterId = $core->recurrenceId() === null ? null : ($masters[$core->uid() ?? ''] ?? null);
            if (! ModelValue::sameIdentifier($event->getAttribute('recurring_master_id'), $masterId)) {
                $event->setAttribute('recurring_master_id', $masterId);
                $event->save();
            }
        }
    }

    /**
     * @param  list<Event>  $events
     * @return array{list<Event>, list<string>, list<string>}
     */
    private function selectRevisions(array $events, TimeZoneResolver $timeZones): array
    {
        $selected = [];
        $discarded = [];
        $invalid = [];
        $invalidKeys = [];
        foreach ($events as $position => $event) {
            $identifier = $event->uid() ?? 'VEVENT['.$position.']';
            try {
                EventIdentity::assertValid($event);
            } catch (\InvalidArgumentException $exception) {
                $invalid[] = $identifier.': '.$exception->getMessage();

                continue;
            }
            $key = EventIdentity::key($event, $timeZones);
            if (isset($invalidKeys[$key])) {
                $invalid[] = $event->uid().': duplicate revision identity was already rejected';

                continue;
            }
            try {
                $this->revisions->compare($event, $event);
            } catch (Throwable $exception) {
                $invalid[] = $event->uid().': '.$exception->getMessage();
                unset($selected[$key]);
                $invalidKeys[$key] = true;

                continue;
            }
            if (! isset($selected[$key])) {
                $selected[$key] = $event;

                continue;
            }
            try {
                $selected[$key] = $this->revisions->preferred($event, $selected[$key]);
                $discarded[] = EventIdentity::key($event, $timeZones);
            } catch (Throwable $exception) {
                $invalid[] = $event->uid().': '.$exception->getMessage();
                unset($selected[$key]);
                $invalidKeys[$key] = true;
            }
        }

        return [array_values($selected), $discarded, $invalid];
    }
}
