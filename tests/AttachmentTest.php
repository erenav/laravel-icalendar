<?php

declare(strict_types=1);

namespace Vanere\LaravelICalendar\Tests;

use Illuminate\Mail\Attachment;
use Vanere\ICalendar\Component\Event;
use Vanere\LaravelICalendar\CalendarAttachment;
use Vanere\LaravelICalendar\Facades\ICalendar;

final class AttachmentTest extends TestCase
{
    public function test_builds_a_mail_attachment(): void
    {
        $calendar = ICalendar::calendar()->add(Event::build()->uid('1@test')->summary('Hi'))->get();

        $attachment = CalendarAttachment::for($calendar, 'invite.ics');

        $this->assertInstanceOf(Attachment::class, $attachment);
        $this->assertSame('invite.ics', $attachment->as);
        $this->assertStringContainsString('text/calendar', (string) $attachment->mime);
    }
}
