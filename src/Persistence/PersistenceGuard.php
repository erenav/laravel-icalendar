<?php

declare(strict_types=1);

namespace Erenav\LaravelICalendar\Persistence;

use LogicException;

final class PersistenceGuard
{
    public function ensureEnabled(): void
    {
        if (config('icalendar.persistence.enabled', false) !== true) {
            throw new LogicException('iCalendar persistence is disabled. Enable [icalendar.persistence.enabled] before using persistence services.');
        }
    }
}
