<?php

declare(strict_types=1);

namespace Vanere\LaravelICalendar\Concerns;

use Vanere\LaravelICalendar\ICalendarManager;

use function Illuminate\Support\enum_value;

/**
 * Helper for Eloquent models implementing
 * {@see \Vanere\LaravelICalendar\Contracts\ProvidesCalendarEvent}.
 *
 * Provides a stable, deterministic UID (so re-exporting the same record yields
 * the same UID rather than duplicating it in calendar clients) and a convenience
 * `toIcs()`.
 */
trait InteractsWithCalendar
{
    /** A deterministic UID for this record, e.g. "Meeting-42@example.com". */
    public function calendarUid(): string
    {
        $key = method_exists($this, 'getKey') ? $this->getKey() : spl_object_id($this);
        $domain = config('icalendar.uid_domain')
            ?: (parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'localhost');

        return sprintf('%s-%s@%s', class_basename(static::class), enum_value($key), $domain);
    }

    /** Serialize this single record as a one-event calendar. */
    public function toIcs(): string
    {
        $manager = app(ICalendarManager::class);

        return $manager->serialize(
            $manager->calendar()->add($this->toCalendarEvent())->get(),
        );
    }
}
