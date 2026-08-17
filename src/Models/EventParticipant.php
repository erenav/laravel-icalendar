<?php

declare(strict_types=1);

namespace Erenav\LaravelICalendar\Models;

use Erenav\LaravelICalendar\Enums\ParticipantType;
use Erenav\LaravelICalendar\Models\Concerns\UsesPersistenceConnection;
use Erenav\LaravelICalendar\Support\ModelRegistry;
use Erenav\LaravelICalendar\Support\TableRegistry;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventParticipant extends Model
{
    use HasUuids;
    use UsesPersistenceConnection;

    protected $table = 'ical_event_participants';

    public function getTable(): string
    {
        $configured = TableRegistry::participant();

        return $configured === 'ical_event_participants' ? parent::getTable() : $configured;
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'type' => ParticipantType::class,
            'rsvp' => 'boolean',
            'member' => 'array',
            'delegated_to' => 'array',
            'delegated_from' => 'array',
            'unknown_parameters' => 'array',
        ];
    }

    /** @return BelongsTo<Model, $this> */
    public function event(): BelongsTo
    {
        return $this->belongsTo(ModelRegistry::event(), 'event_id');
    }
}
