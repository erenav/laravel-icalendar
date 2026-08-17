<?php

declare(strict_types=1);

namespace Erenav\LaravelICalendar\Enums;

enum TemporalType: string
{
    case Date = 'date';
    case Floating = 'floating';
    case Utc = 'utc';
    case Zoned = 'zoned';
}
