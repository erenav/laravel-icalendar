<?php

declare(strict_types=1);

namespace Erenav\LaravelICalendar\Support;

use Erenav\LaravelICalendar\Models\Calendar;
use Erenav\LaravelICalendar\Models\CalendarEvent;
use Erenav\LaravelICalendar\Models\EventAlarm;
use Erenav\LaravelICalendar\Models\EventParticipant;
use Illuminate\Database\Eloquent\Model;
use LogicException;

final class ModelRegistry
{
    /** @return class-string<Model> */
    public static function calendar(): string
    {
        return self::model('calendar', Calendar::class);
    }

    /** @return class-string<Model> */
    public static function event(): string
    {
        return self::model('event', CalendarEvent::class);
    }

    /** @return class-string<Model> */
    public static function participant(): string
    {
        return self::model('participant', EventParticipant::class);
    }

    /** @return class-string<Model> */
    public static function alarm(): string
    {
        return self::model('alarm', EventAlarm::class);
    }

    /**
     * @param  class-string<Model>  $default
     * @return class-string<Model>
     */
    private static function model(string $key, string $default): string
    {
        $configured = config("icalendar.persistence.models.{$key}", $default);

        if (! is_string($configured) || ! is_a($configured, Model::class, true)) {
            throw new LogicException("Configured iCalendar model [{$key}] must extend Eloquent Model.");
        }

        return $configured;
    }
}
