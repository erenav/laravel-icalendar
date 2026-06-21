<?php

declare(strict_types=1);

namespace Erenav\LaravelICalendar\Tests;

use Erenav\ICalendar\Component\Event;
use Erenav\LaravelICalendar\CalendarResponse;
use Erenav\LaravelICalendar\Facades\ICalendar;

final class HttpResponseTest extends TestCase
{
    private function calendar()
    {
        return ICalendar::calendar()->add(Event::build()->uid('1@test')->summary('Hi'))->get();
    }

    public function test_calendar_response_sets_headers_and_body(): void
    {
        $response = (new CalendarResponse($this->calendar(), 'feed.ics'))->toResponse(request());

        $this->assertSame('text/calendar; charset=utf-8', $response->headers->get('Content-Type'));
        $this->assertSame('attachment; filename="feed.ics"', $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('SUMMARY:Hi', (string) $response->getContent());
    }

    public function test_manager_response_uses_default_filename(): void
    {
        $response = ICalendar::response($this->calendar())->toResponse(request());
        $this->assertSame('attachment; filename="calendar.ics"', $response->headers->get('Content-Disposition'));
    }

    public function test_response_macro_is_registered(): void
    {
        $result = response()->ics($this->calendar(), 'macro.ics');

        $this->assertInstanceOf(CalendarResponse::class, $result);
        $this->assertSame(
            'attachment; filename="macro.ics"',
            $result->toResponse(request())->headers->get('Content-Disposition'),
        );
    }

    public function test_response_is_returnable_from_a_route(): void
    {
        $this->app['router']->get('/feed.ics', fn () => ICalendar::response($this->calendar(), 'feed.ics'));

        $this->get('/feed.ics')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/calendar; charset=utf-8')
            ->assertSee('SUMMARY:Hi', false);
    }
}
