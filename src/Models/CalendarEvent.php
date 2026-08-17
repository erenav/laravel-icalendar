<?php

declare(strict_types=1);

namespace Erenav\LaravelICalendar\Models;

use Erenav\LaravelICalendar\Enums\EventComponentType;
use Erenav\LaravelICalendar\Enums\TemporalType;
use Erenav\LaravelICalendar\Models\Concerns\UsesPersistenceConnection;
use Erenav\LaravelICalendar\Support\ModelRegistry;
use Erenav\LaravelICalendar\Support\TableRegistry;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CalendarEvent extends Model
{
    use HasUuids;
    use UsesPersistenceConnection;

    protected $table = 'ical_calendar_events';

    public function getTable(): string
    {
        $configured = TableRegistry::event();

        return $configured === 'ical_calendar_events' ? parent::getTable() : $configured;
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'component_type' => EventComponentType::class,
            'recurrence_id_type' => TemporalType::class,
            'dtstart_type' => TemporalType::class,
            'dtend_type' => TemporalType::class,
            'dtstart_utc' => 'immutable_datetime',
            'dtend_utc' => 'immutable_datetime',
            'ical_created_at' => 'immutable_datetime',
            'ical_dtstamp' => 'immutable_datetime',
            'ical_last_modified_at' => 'immutable_datetime',
            'is_cancelled' => 'boolean',
        ];
    }

    /** @return BelongsTo<Model, $this> */
    public function calendar(): BelongsTo
    {
        return $this->belongsTo(ModelRegistry::calendar(), 'calendar_id');
    }

    /** @return BelongsTo<Model, $this> */
    public function recurringMaster(): BelongsTo
    {
        return $this->belongsTo(ModelRegistry::event(), 'recurring_master_id');
    }

    /** @return HasMany<Model, $this> */
    public function overrides(): HasMany
    {
        return $this->hasMany(ModelRegistry::event(), 'recurring_master_id');
    }

    /** @return HasMany<Model, $this> */
    public function participants(): HasMany
    {
        return $this->hasMany(ModelRegistry::participant(), 'event_id')->orderBy('position');
    }

    /** @return HasMany<Model, $this> */
    public function alarms(): HasMany
    {
        return $this->hasMany(ModelRegistry::alarm(), 'event_id')->orderBy('position');
    }
}
