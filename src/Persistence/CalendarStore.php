<?php

declare(strict_types=1);

namespace Erenav\LaravelICalendar\Persistence;

use Erenav\ICalendar\Component\Calendar as CoreCalendar;
use Erenav\ICalendar\Component\Event;
use Erenav\ICalendar\Recurrence\EventRevisionComparator;
use Erenav\ICalendar\TimeZone\TimeZoneResolver;
use Erenav\LaravelICalendar\Enums\CalendarSourceType;
use Erenav\LaravelICalendar\Importing\CalendarImporter;
use Erenav\LaravelICalendar\Importing\ImportResult;
use Erenav\LaravelICalendar\Support\ModelRegistry;
use Erenav\LaravelICalendar\Support\ModelValue;
use Illuminate\Database\Eloquent\Model;

final class CalendarStore
{
    public function __construct(
        private readonly CalendarImporter $importer,
        private readonly CoreEventMapper $events,
        private readonly CoreComponentCodec $codec,
        private readonly PersistenceGuard $guard,
        private readonly EventRevisionComparator $revisions,
    ) {}

    public function create(
        string $name,
        ?string $description = null,
        ?string $timezone = null,
        ?string $color = null,
        CalendarSourceType $sourceType = CalendarSourceType::Internal,
    ): Model {
        $this->guard->ensureEnabled();
        $class = ModelRegistry::calendar();
        $calendar = new $class;
        $calendar->setAttribute('name', $name);
        $calendar->setAttribute('description', $description);
        $calendar->setAttribute('timezone', $timezone);
        $calendar->setAttribute('color', $color);
        $calendar->setAttribute('source_type', $sourceType);
        $calendar->setAttribute('enabled', true);
        $builder = CoreCalendar::build()->name($name);
        $productId = config('icalendar.product_id');
        if (is_string($productId) && $productId !== '') {
            $builder->prodId($productId);
        }
        if ($description !== null) {
            $builder->property('X-WR-CALDESC', $description);
        }
        if ($timezone !== null) {
            $builder->property('X-WR-TIMEZONE', $timezone);
        }
        if ($color !== null) {
            $builder->property('COLOR', $color);
        }
        $calendar->setAttribute('component_ics', $this->codec->encodeCalendarEnvelope($builder->get()));
        $calendar->save();

        return $calendar;
    }

    public function import(Model $calendar, CoreCalendar $source): ImportResult
    {
        $this->guard->ensureEnabled();

        return $this->importer->importCalendar($calendar, $source);
    }

    public function createFromCore(
        CoreCalendar $source,
        CalendarSourceType $sourceType = CalendarSourceType::Imported,
    ): Model {
        $this->guard->ensureEnabled();
        $class = ModelRegistry::calendar();
        $connection = (new $class)->getConnection();

        return $connection->transaction(function () use ($source, $sourceType): Model {
            $calendar = $this->create(
                $source->name() ?? 'Calendar',
                $source->property('X-WR-CALDESC')?->value()->toString(),
                $source->property('X-WR-TIMEZONE')?->value()->toString(),
                $source->property('COLOR')?->value()->toString(),
                $sourceType,
            );
            $result = $this->importer->importCalendar($calendar, $source);
            if ($result->invalid !== []) {
                throw new \InvalidArgumentException(
                    'Cannot persist an invalid calendar: '.implode('; ', $result->invalid),
                );
            }

            return $calendar;
        });
    }

    public function assignOwner(Model $calendar, ?string $type, ?string $identifier): void
    {
        $this->guard->ensureEnabled();
        if (($type !== null || $identifier !== null) && ! (bool) config('icalendar.persistence.owner.enabled', false)) {
            throw new \LogicException('Optional iCalendar ownership is disabled.');
        }
        if (($type === null) !== ($identifier === null)) {
            throw new \InvalidArgumentException('Owner type and identifier must both be null or both be present.');
        }

        $calendar->setAttribute('owner_type', $type);
        $calendar->setAttribute('owner_id', $identifier);
        $calendar->save();
    }

    public function putEvent(Model $calendar, Event $event): Model
    {
        $this->guard->ensureEnabled();
        EventIdentity::assertValid($event);
        $this->revisions->compare($event, $event);
        $class = ModelRegistry::event();
        $timeZones = $this->timeZones($calendar);
        $record = $class::query()
            ->where('calendar_id', $calendar->getKey())
            ->where('identity_hash', EventIdentity::hash($event, $timeZones))
            ->first();
        if ($record === null) {
            $candidates = $class::query()
                ->where('calendar_id', $calendar->getKey())
                ->where('uid_hash', hash('sha256', $event->uid() ?? ''))
                ->get();
            foreach ($candidates as $candidate) {
                if (EventIdentity::matches($this->events->toCore($candidate), $event, $timeZones)) {
                    $record = $candidate;

                    break;
                }
            }
        }
        $record ??= new $class;

        $this->replaceEvent($record, $event, ModelValue::key($calendar));

        return $record;
    }

    public function replaceEvent(
        Model $record,
        Event $event,
        ?string $calendarId = null,
    ): Model {
        $this->guard->ensureEnabled();
        EventIdentity::assertValid($event);
        $this->revisions->compare($event, $event);
        $calendarId ??= ModelValue::string($record, 'calendar_id');
        $calendarClass = ModelRegistry::calendar();
        $calendar = $calendarClass::query()->whereKey($calendarId)->firstOrFail();
        $timeZones = $this->timeZones($calendar);

        $record->getConnection()->transaction(function () use ($record, $event, $calendarId, $timeZones): void {
            $this->events->fill($record, $calendarId, $event, $timeZones);
            $record->save();
            $this->events->reconcileChildren($record, $event);
            $this->linkSeriesComponent($record, $event);
        });

        return $record;
    }

    /**
     * @param  iterable<Event>  $overrides
     * @return list<Model>
     */
    public function upsertRecurringSeries(Model $calendar, Event $master, iterable $overrides): array
    {
        $this->guard->ensureEnabled();
        if ($master->recurrenceId() !== null || ! $master->isRecurring()) {
            throw new \InvalidArgumentException('A recurring series master must have recurrence data and no RECURRENCE-ID.');
        }

        return $calendar->getConnection()->transaction(function () use ($calendar, $master, $overrides): array {
            $records = [$this->putEvent($calendar, $master)];
            foreach ($overrides as $override) {
                if ($override->recurrenceId() === null || $override->uid() !== $master->uid()) {
                    throw new \InvalidArgumentException('Each detached override must share the master UID and have a RECURRENCE-ID.');
                }
                $records[] = $this->putEvent($calendar, $override);
            }

            return $records;
        });
    }

    private function linkSeriesComponent(Model $record, Event $event): void
    {
        $class = ModelRegistry::event();
        $linkedOverrides = $class::query()->where('recurring_master_id', $record->getKey());
        $series = $class::query()
            ->where('calendar_id', $record->getAttribute('calendar_id'))
            ->where('uid_hash', hash('sha256', $event->uid() ?? ''));

        if ($event->recurrenceId() !== null) {
            $linkedOverrides->update(['recurring_master_id' => null]);
            $master = (clone $series)->whereNull('recurrence_id_value')->where('component_type', 'recurring_master')->first();
            $record->setAttribute('recurring_master_id', $master?->getKey());
            $record->save();

            return;
        }
        if (! $event->isRecurring()) {
            $linkedOverrides->update(['recurring_master_id' => null]);
            $record->setAttribute('recurring_master_id', null);
            $record->save();

            return;
        }

        $linkedOverrides
            ->where(function ($query) use ($record, $event): void {
                $query->where('calendar_id', '!=', $record->getAttribute('calendar_id'))
                    ->orWhere('uid_hash', '!=', hash('sha256', $event->uid() ?? ''));
            })
            ->update(['recurring_master_id' => null]);
        $record->setAttribute('recurring_master_id', null);
        $record->save();

        $class::query()
            ->where('calendar_id', $record->getAttribute('calendar_id'))
            ->where('uid_hash', hash('sha256', $event->uid() ?? ''))
            ->whereNotNull('recurrence_id_value')
            ->update(['recurring_master_id' => $record->getKey()]);
    }

    private function timeZones(Model $calendar): TimeZoneResolver
    {
        $ics = $calendar->getAttribute('component_ics');
        $envelope = $this->codec->decodeCalendarEnvelope(is_string($ics) ? $ics : null);

        return TimeZoneResolver::fromCalendar($envelope);
    }
}
