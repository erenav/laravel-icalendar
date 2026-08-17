<?php

declare(strict_types=1);

namespace Erenav\LaravelICalendar\Persistence;

use Erenav\ICalendar\Component\Event;
use Erenav\ICalendar\Parameter\RawParameter;
use Erenav\ICalendar\Property\Attendee;
use Erenav\ICalendar\Property\Organizer;
use Erenav\ICalendar\Property\Property;
use Erenav\ICalendar\Property\PropertyBag;
use Erenav\ICalendar\Serializer\IcsSerializer;
use Erenav\ICalendar\TimeZone\TimeZoneResolver;
use Erenav\ICalendar\ValueType\DateTimeValue;
use Erenav\ICalendar\ValueType\Duration;
use Erenav\LaravelICalendar\Enums\EventComponentType;
use Erenav\LaravelICalendar\Enums\ParticipantType;
use Erenav\LaravelICalendar\Support\ModelRegistry;
use Erenav\LaravelICalendar\Support\ModelValue;
use Illuminate\Database\Eloquent\Model;

final class CoreEventMapper
{
    public function __construct(private readonly CoreComponentCodec $codec) {}

    public function toCore(Model $record): Event
    {
        return $this->codec->decodeEvent(ModelValue::string($record, 'component_ics'));
    }

    public function fill(
        Model $record,
        string $calendarId,
        Event $event,
        ?TimeZoneResolver $timeZones = null,
    ): void {
        $start = $event->start();
        $explicitEnd = $event->property('DTEND')?->value();
        $end = $explicitEnd instanceof DateTimeValue ? $explicitEnd : null;
        $recurrenceId = $event->recurrenceId();
        $uid = $event->uid() ?? '';

        $record->setAttribute('calendar_id', $calendarId);
        $record->setAttribute('identity_hash', EventIdentity::hash($event, $timeZones));
        $record->setAttribute('uid_hash', hash('sha256', $uid));
        $record->setAttribute('uid', $uid);
        $record->setAttribute('component_type', match (true) {
            $recurrenceId !== null => EventComponentType::DetachedOverride,
            $event->isRecurring() => EventComponentType::RecurringMaster,
            default => EventComponentType::Standalone,
        });
        $this->setTemporal($record, 'recurrence_id', $recurrenceId, $timeZones);
        $record->setAttribute('recurrence_range', $event->recurrenceRange()?->value);
        $record->setAttribute('summary', $event->summary());
        $record->setAttribute('description', $event->description());
        $record->setAttribute('location', $event->location());
        $record->setAttribute('url', $event->url());
        $this->setTemporal($record, 'dtstart', $start, $timeZones);
        $this->setTemporal($record, 'dtend', $end, $timeZones);
        $record->setAttribute('duration', $event->duration()?->toString());
        $record->setAttribute('source_timezone', $start?->tzid);
        $record->setAttribute('status', $event->status()?->value);
        $record->setAttribute('transparency', $event->transparency()?->value);
        $record->setAttribute('classification', $event->classification()?->value);
        $record->setAttribute('priority', $event->priority());
        $record->setAttribute('sequence', $event->sequence());
        $record->setAttribute('color', $event->color());
        $record->setAttribute('ical_created_at', $this->utc($event->created()));
        $record->setAttribute('ical_dtstamp', $this->utc($event->timestamp()));
        $record->setAttribute('ical_last_modified_at', $this->utc($event->lastModified()));
        $record->setAttribute('rrule', $event->recurrenceRule()?->toString());
        $record->setAttribute('is_cancelled', $event->isCancelled());
        $record->setAttribute('component_ics', $this->codec->encode($event));
    }

    public function reconcileChildren(Model $record, Event $event): void
    {
        $record->getConnection()->transaction(function () use ($record, $event): void {
            $participantClass = ModelRegistry::participant();
            $participantClass::query()->where('event_id', $record->getKey())->delete();

            $position = 0;
            if (($organizer = $event->organizer()) !== null) {
                $this->storeOrganizer($record, $organizer, $position++);
            }
            foreach ($event->attendees() as $attendee) {
                $this->storeAttendee($record, $attendee, $position++);
            }

            $alarmClass = ModelRegistry::alarm();
            $alarmClass::query()->where('event_id', $record->getKey())->delete();
            foreach ($event->alarms() as $alarmPosition => $alarm) {
                $model = new $alarmClass;
                $model->setAttribute('event_id', $record->getKey());
                $model->setAttribute('position', $alarmPosition);
                $model->setAttribute('action', $alarm->action()?->value);
                $model->setAttribute('trigger_value', $alarm->trigger()?->toString());
                $model->setAttribute('trigger_type', match (true) {
                    $alarm->trigger() instanceof Duration => 'duration',
                    $alarm->trigger() instanceof DateTimeValue => 'date_time',
                    $alarm->trigger() === null => null,
                    default => 'raw',
                });
                $model->setAttribute('description', $alarm->description());
                $model->setAttribute('summary', $alarm->summary());
                $model->setAttribute('repeat_count', $alarm->repeatCount());
                $model->setAttribute('repeat_duration', $alarm->duration()?->toString());
                $model->setAttribute('component_ics', $this->codec->encode($alarm));
                $model->save();
            }
        });
    }

    private function setTemporal(Model $record, string $prefix, ?DateTimeValue $value, ?TimeZoneResolver $resolver): void
    {
        $record->setAttribute($prefix.'_value', $value?->toString());
        $record->setAttribute($prefix.'_type', $value === null ? null : TemporalSemantics::type($value));
        $record->setAttribute($prefix.'_timezone', $value?->tzid);
        if ($prefix !== 'recurrence_id') {
            $record->setAttribute($prefix.'_utc', $value === null ? null : TemporalSemantics::utc($value, $resolver));
        }
    }

    private function storeOrganizer(Model $eventRecord, Organizer $organizer, int $position): void
    {
        $model = $this->participant($eventRecord, $organizer->property, ParticipantType::Organizer, $position);
        $model->setAttribute('calendar_address', $organizer->address()->toString());
        $model->setAttribute('common_name', $organizer->commonName());
        $model->setAttribute('sent_by', $organizer->sentByAddress()?->toString());
        $model->setAttribute('directory', $organizer->directory());
        $model->setAttribute('language', $organizer->language());
        $model->save();
    }

    private function storeAttendee(Model $eventRecord, Attendee $attendee, int $position): void
    {
        $model = $this->participant($eventRecord, $attendee->property, ParticipantType::Attendee, $position);
        $model->setAttribute('calendar_address', $attendee->address()->toString());
        $model->setAttribute('common_name', $attendee->commonName());
        $model->setAttribute('role', $attendee->role()?->value);
        $model->setAttribute('participation_status', $attendee->participationStatus()?->value);
        $model->setAttribute('user_type', $attendee->userType()?->value);
        $model->setAttribute('rsvp', $attendee->rsvp());
        $model->setAttribute('member', array_map(static fn ($address): string => $address->toString(), $attendee->members()));
        $model->setAttribute('delegated_to', array_map(static fn ($address): string => $address->toString(), $attendee->delegatedTo()));
        $model->setAttribute('delegated_from', array_map(static fn ($address): string => $address->toString(), $attendee->delegatedFrom()));
        $model->setAttribute('sent_by', $attendee->sentBy()?->toString());
        $model->setAttribute('directory', $attendee->directory());
        $model->setAttribute('language', $attendee->language());
        $model->save();
    }

    private function participant(Model $eventRecord, Property $property, ParticipantType $type, int $position): Model
    {
        $class = ModelRegistry::participant();
        $model = new $class;
        $model->setAttribute('event_id', $eventRecord->getKey());
        $model->setAttribute('position', $position);
        $model->setAttribute('type', $type);
        $model->setAttribute('unknown_parameters', $this->unknownParameters($property));
        $model->setAttribute('property_ics', (new IcsSerializer)->serialize(new Event(new PropertyBag($property))));

        return $model;
    }

    /** @return array<string, list<string>> */
    private function unknownParameters(Property $property): array
    {
        $known = ['CN', 'ROLE', 'PARTSTAT', 'CUTYPE', 'RSVP', 'MEMBER', 'DELEGATED-TO', 'DELEGATED-FROM', 'SENT-BY', 'DIR', 'LANGUAGE'];
        $unknown = [];
        foreach ($property->parameters as $parameter) {
            $name = $parameter instanceof RawParameter ? $parameter->name : strtoupper($parameter->parameterName());
            if (in_array($name, $known, true)) {
                continue;
            }
            $unknown[$name] = $parameter instanceof RawParameter
                ? $parameter->values
                : [$parameter->token()];
        }

        return $unknown;
    }

    private function utc(?DateTimeValue $value): ?\DateTimeImmutable
    {
        return $value === null ? null : TemporalSemantics::utc($value);
    }
}
