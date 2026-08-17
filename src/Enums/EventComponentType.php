<?php

declare(strict_types=1);

namespace Erenav\LaravelICalendar\Enums;

enum EventComponentType: string
{
    case Standalone = 'standalone';
    case RecurringMaster = 'recurring_master';
    case DetachedOverride = 'detached_override';
}
