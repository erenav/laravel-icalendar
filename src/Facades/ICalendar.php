<?php

declare(strict_types=1);

namespace Vanere\LaravelICalendar\Facades;

use Illuminate\Support\Facades\Facade;
use Vanere\ICalendar\Builder\CalendarBuilder;
use Vanere\ICalendar\Builder\EventBuilder;
use Vanere\ICalendar\Component\Calendar;
use Vanere\ICalendar\Component\Component;
use Vanere\LaravelICalendar\CalendarResponse;
use Vanere\LaravelICalendar\ICalendarManager;

/**
 * @method static CalendarBuilder calendar()
 * @method static EventBuilder event()
 * @method static Calendar parse(string $ics)
 * @method static string serialize(Component $component)
 * @method static Calendar fromModels(iterable<\Vanere\LaravelICalendar\Contracts\ProvidesCalendarEvent> $models)
 * @method static CalendarResponse response(Component $component, ?string $filename = null)
 *
 * @see ICalendarManager
 */
final class ICalendar extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return ICalendarManager::class;
    }
}
