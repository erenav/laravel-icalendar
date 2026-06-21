<?php

declare(strict_types=1);

namespace Erenav\LaravelICalendar\Tests;

use Erenav\ICalendar\Component\Event;
use Erenav\LaravelICalendar\CalendarAttachment;
use Erenav\LaravelICalendar\Facades\ICalendar;
use Illuminate\Mail\Attachment;

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

    public function test_itip_method_is_advertised_in_mime(): void
    {
        $calendar = ICalendar::calendar()
            ->method('REQUEST')
            ->add(Event::build()->uid('1@test')->summary('Invite'))
            ->get();

        $attachment = CalendarAttachment::for($calendar);

        $this->assertStringContainsString('method=REQUEST', (string) $attachment->mime);
    }
}
