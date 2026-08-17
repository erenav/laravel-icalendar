<?php

declare(strict_types=1);

namespace Erenav\LaravelICalendar\Enums;

enum CalendarSourceType: string
{
    case Internal = 'internal';
    case Imported = 'imported';
}
