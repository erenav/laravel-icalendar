<?php

declare(strict_types=1);

namespace Erenav\LaravelICalendar\Facades;

use Erenav\ICalendar\Builder\CalendarBuilder;
use Erenav\ICalendar\Builder\EventBuilder;
use Erenav\ICalendar\Component\Calendar;
use Erenav\ICalendar\Component\Component;
use Erenav\LaravelICalendar\CalendarResponse;
use Erenav\LaravelICalendar\ICalendarManager;
use Illuminate\Support\Facades\Facade;

/**
 * @method static CalendarBuilder calendar()
 * @method static EventBuilder event()
 * @method static Calendar parse(string $ics)
 * @method static string serialize(Component $component)
 * @method static Calendar fromModels(iterable<\Erenav\LaravelICalendar\Contracts\ProvidesCalendarEvent> $models)
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
