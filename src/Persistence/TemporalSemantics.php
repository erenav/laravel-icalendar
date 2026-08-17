<?php

declare(strict_types=1);

namespace Erenav\LaravelICalendar\Persistence;

use DateTimeImmutable;
use DateTimeZone;
use Erenav\ICalendar\TimeZone\TimeZoneResolver;
use Erenav\ICalendar\ValueType\DateTimeValue;
use Erenav\LaravelICalendar\Enums\TemporalType;
use Throwable;

final class TemporalSemantics
{
    public static function type(DateTimeValue $value): TemporalType
    {
        return match (true) {
            $value->isDateOnly => TemporalType::Date,
            $value->isUtc => TemporalType::Utc,
            $value->tzid !== null => TemporalType::Zoned,
            default => TemporalType::Floating,
        };
    }

    public static function utc(DateTimeValue $value, ?TimeZoneResolver $resolver = null): ?DateTimeImmutable
    {
        if ($value->isDateOnly || $value->isFloating()) {
            return null;
        }

        try {
            $instant = $resolver !== null
                ? $resolver->instant($value)
                : ($value->hasResolvedInstant() ? $value->dateTime : null);

            return $instant?->setTimezone(new DateTimeZone('UTC'));
        } catch (Throwable) {
            return null;
        }
    }
}
