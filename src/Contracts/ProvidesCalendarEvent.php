<?php

declare(strict_types=1);

namespace Erenav\LaravelICalendar\Contracts;

use Erenav\ICalendar\Component\Event;

/**
 * Implemented by models (or any object) that can represent themselves as a
 * calendar event, so they can be collected into a calendar or served as a feed.
 */
interface ProvidesCalendarEvent
{
    public function toCalendarEvent(): Event;
}
