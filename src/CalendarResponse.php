<?php

declare(strict_types=1);

namespace Erenav\LaravelICalendar;

use Erenav\ICalendar\Component\Component;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\Response;

/**
 * A downloadable `text/calendar` HTTP response. Return it directly from a
 * controller (it is {@see Responsable}) or build it via `ICalendar::response()`
 * or the `response()->ics()` macro.
 */
final class CalendarResponse implements Responsable
{
    public function __construct(
        private readonly Component $component,
        private readonly string $filename = 'calendar.ics',
        private readonly ?ICalendarManager $manager = null,
    ) {}

    public function toResponse($request): Response
    {
        $manager = $this->manager ?? app(ICalendarManager::class);

        return new Response($manager->serialize($this->component), Response::HTTP_OK, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => sprintf('attachment; filename="%s"', $this->filename),
        ]);
    }
}
