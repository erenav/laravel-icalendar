<?php

declare(strict_types=1);

namespace Erenav\LaravelICalendar\Models;

use Erenav\LaravelICalendar\Models\Concerns\UsesPersistenceConnection;
use Erenav\LaravelICalendar\Support\ModelRegistry;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventAlarm extends Model
{
    use HasUuids;
    use UsesPersistenceConnection;

    protected $table = 'ical_event_alarms';

    /** @return BelongsTo<Model, $this> */
    public function event(): BelongsTo
    {
        return $this->belongsTo(ModelRegistry::event(), 'event_id');
    }
}
