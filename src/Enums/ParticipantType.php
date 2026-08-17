<?php

declare(strict_types=1);

namespace Erenav\LaravelICalendar\Enums;

enum ParticipantType: string
{
    case Organizer = 'organizer';
    case Attendee = 'attendee';
}
