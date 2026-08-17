<?php

declare(strict_types=1);

namespace Erenav\LaravelICalendar\Persistence;

use Erenav\ICalendar\Component\Event;
use Erenav\ICalendar\Parameter\Range;
use Erenav\ICalendar\Parameter\RawParameter;
use Erenav\ICalendar\TimeZone\TimeZoneResolver;
use Erenav\ICalendar\ValueType\DateTimeValue;
use Erenav\ICalendar\ValueType\TextValue;
use Throwable;

final class EventIdentity
{
    public static function assertValid(Event $event): void
    {
        $uids = $event->properties->all('UID');
        if (count($uids) !== 1
            || count($uids[0]->values) !== 1
            || ! $uids[0]->value() instanceof TextValue
            || $uids[0]->value()->toString() === '') {
            throw new \InvalidArgumentException('A persisted VEVENT must contain exactly one non-empty, typed UID property.');
        }

        $recurrenceIds = $event->properties->all('RECURRENCE-ID');
        if (count($recurrenceIds) > 1
            || ($recurrenceIds !== []
                && (count($recurrenceIds[0]->values) !== 1
                    || ! $recurrenceIds[0]->value() instanceof DateTimeValue))) {
            throw new \InvalidArgumentException('A persisted VEVENT must contain at most one typed, single-valued RECURRENCE-ID property.');
        }

        $range = $recurrenceIds === [] ? null : $recurrenceIds[0]->parameter('RANGE');
        $validRawRange = $range instanceof RawParameter
            && count($range->values) === 1
            && strtoupper($range->value()) === Range::ThisAndFuture->value;
        if ($range !== null && ! $range instanceof Range && ! $validRawRange) {
            throw new \InvalidArgumentException('A persisted RECURRENCE-ID RANGE must be the single value THISANDFUTURE.');
        }
    }

    public static function hash(Event $event, ?TimeZoneResolver $timeZones = null): string
    {
        return hash('sha256', self::key($event, $timeZones));
    }

    public static function key(Event $event, ?TimeZoneResolver $timeZones = null): string
    {
        return ($event->uid() ?? '')."\0".self::recurrenceKey($event->recurrenceId(), $timeZones);
    }

    public static function recurrenceKey(?DateTimeValue $value, ?TimeZoneResolver $timeZones = null): string
    {
        if ($value === null) {
            return '-';
        }

        if ($value->isDateOnly) {
            return 'date|'.$value->toString();
        }
        if ($value->isFloating()) {
            return 'floating|'.$value->toString();
        }

        try {
            $instant = $timeZones !== null
                ? $timeZones->instant($value)
                : ($value->hasResolvedInstant() ? $value->dateTime : null);
            if ($instant !== null) {
                return 'instant|'.$instant->getTimestamp();
            }
        } catch (Throwable) {
            // Preserve a deterministic unresolved identity below. Import does
            // not invent an instant when a custom TZID cannot be resolved.
        }

        return 'zoned|'.($value->tzid ?? '').'|'.$value->toString();
    }

    public static function sameRecurrenceInstance(
        ?DateTimeValue $left,
        ?DateTimeValue $right,
        ?TimeZoneResolver $timeZones = null,
    ): bool {
        return self::recurrenceKey($left, $timeZones) === self::recurrenceKey($right, $timeZones)
            || self::lexicalRecurrenceKey($left) === self::lexicalRecurrenceKey($right);
    }

    public static function matches(Event $left, Event $right, ?TimeZoneResolver $timeZones = null): bool
    {
        return $left->uid() === $right->uid()
            && self::sameRecurrenceInstance($left->recurrenceId(), $right->recurrenceId(), $timeZones);
    }

    private static function lexicalRecurrenceKey(?DateTimeValue $value): string
    {
        if ($value === null) {
            return '-';
        }

        return match (true) {
            $value->isDateOnly => 'date|'.$value->toString(),
            $value->isFloating() => 'floating|'.$value->toString(),
            $value->isUtc => 'utc|'.$value->toString(),
            default => 'zoned|'.($value->tzid ?? '').'|'.$value->toString(),
        };
    }
}
