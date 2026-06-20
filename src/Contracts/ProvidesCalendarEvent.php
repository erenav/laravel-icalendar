<?php

declare(strict_types=1);

namespace Vanere\LaravelICalendar\Contracts;

use Vanere\ICalendar\Component\Event;

/**
 * Implemented by models (or any object) that can represent themselves as a
 * calendar event, so they can be collected into a calendar or served as a feed.
 */
interface ProvidesCalendarEvent
{
    public function toCalendarEvent(): Event;
}
