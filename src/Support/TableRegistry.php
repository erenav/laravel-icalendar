<?php

declare(strict_types=1);

namespace Erenav\LaravelICalendar\Support;

final class TableRegistry
{
    /** @var array<string, string> */
    private const DEFAULTS = [
        'calendar' => 'ical_calendars',
        'event' => 'ical_calendar_events',
        'participant' => 'ical_event_participants',
        'alarm' => 'ical_event_alarms',
    ];

    public static function calendar(): string
    {
        return self::resolve('calendar');
    }

    public static function event(): string
    {
        return self::resolve('event');
    }

    public static function participant(): string
    {
        return self::resolve('participant');
    }

    public static function alarm(): string
    {
        return self::resolve('alarm');
    }

    private static function resolve(string $key): string
    {
        $configured = config("icalendar.persistence.tables.{$key}");

        return is_string($configured) && $configured !== '' ? $configured : self::DEFAULTS[$key];
    }
}
