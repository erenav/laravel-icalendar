<?php

declare(strict_types=1);

namespace Erenav\LaravelICalendar\Models;

use Erenav\LaravelICalendar\Enums\CalendarSourceType;
use Erenav\LaravelICalendar\Models\Concerns\UsesPersistenceConnection;
use Erenav\LaravelICalendar\Support\ModelRegistry;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Calendar extends Model
{
    use HasUuids;
    use UsesPersistenceConnection;

    protected $table = 'ical_calendars';

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'source_type' => CalendarSourceType::class,
            'enabled' => 'boolean',
        ];
    }

    /** @return MorphTo<Model, $this> */
    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return HasMany<Model, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(ModelRegistry::event(), 'calendar_id');
    }
}
