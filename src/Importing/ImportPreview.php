<?php

declare(strict_types=1);

namespace Erenav\LaravelICalendar\Importing;

use Erenav\ICalendar\Component\Calendar;
use Erenav\ICalendar\Component\Event;

final readonly class ImportPreview
{
    public int $discardedRevisions;

    /**
     * @param  list<Event>  $selectedEvents
     * @param  list<string>  $discardedRevisionIds
     * @param  list<string>  $invalid
     */
    public function __construct(
        public Calendar $calendar,
        public array $selectedEvents,
        public array $discardedRevisionIds,
        public array $invalid,
    ) {
        $this->discardedRevisions = count($discardedRevisionIds);
    }
}
