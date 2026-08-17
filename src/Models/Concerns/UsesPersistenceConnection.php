<?php

declare(strict_types=1);

namespace Erenav\LaravelICalendar\Models\Concerns;

trait UsesPersistenceConnection
{
    public function getConnectionName(): ?string
    {
        $configured = config('icalendar.persistence.connection');

        return is_string($configured) && $configured !== '' ? $configured : parent::getConnectionName();
    }
}
