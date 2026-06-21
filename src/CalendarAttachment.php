<?php

declare(strict_types=1);

namespace Erenav\LaravelICalendar;

use Erenav\ICalendar\Component\Calendar;
use Erenav\ICalendar\Component\Component;
use Illuminate\Mail\Attachment;

/**
 * Builds a mail {@see Attachment} from a calendar component, for use in
 * Mailables and notification `MailMessage`s:
 *
 *     $message->attach(CalendarAttachment::for($calendar));
 *
 * When the calendar carries an iTIP METHOD (e.g. REQUEST), it is advertised in
 * the MIME type so mail clients treat the attachment as an invitation.
 */
final class CalendarAttachment
{
    public static function for(
        Component $component,
        string $filename = 'invite.ics',
        ?ICalendarManager $manager = null,
    ): Attachment {
        $manager ??= app(ICalendarManager::class);

        $mime = 'text/calendar; charset=utf-8';
        if ($component instanceof Calendar && ($method = $component->method()) !== null) {
            $mime .= '; method='.strtoupper($method);
        }

        return Attachment::fromData(static fn (): string => $manager->serialize($component), $filename)
            ->withMime($mime);
    }
}
