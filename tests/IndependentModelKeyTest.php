<?php

declare(strict_types=1);

namespace Erenav\LaravelICalendar\Tests;

use Erenav\ICalendar\Component\Alarm;
use Erenav\ICalendar\Component\Event;
use Erenav\ICalendar\Property\AlarmAction;
use Erenav\ICalendar\ValueType\Duration;
use Erenav\LaravelICalendar\Enums\CalendarSourceType;
use Erenav\LaravelICalendar\Enums\EventComponentType;
use Erenav\LaravelICalendar\Enums\ParticipantType;
use Erenav\LaravelICalendar\Enums\TemporalType;
use Erenav\LaravelICalendar\Importing\CalendarImporter;
use Erenav\LaravelICalendar\Models\Calendar;
use Erenav\LaravelICalendar\Models\Concerns\UsesPersistenceConnection;
use Erenav\LaravelICalendar\Persistence\CalendarStore;
use Erenav\LaravelICalendar\Persistence\StoredCalendarExporter;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class BigIntCalendar extends Model
{
    use UsesPersistenceConnection;

    protected $table = 'bigint_calendars';

    protected function casts(): array
    {
        return [
            'source_type' => CalendarSourceType::class,
            'enabled' => 'boolean',
        ];
    }
}

final class BigIntCalendarEvent extends Model
{
    use UsesPersistenceConnection;

    protected $table = 'bigint_calendar_events';

    protected function casts(): array
    {
        return [
            'calendar_id' => 'integer',
            'recurring_master_id' => 'integer',
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
}

final class BigIntEventParticipant extends Model
{
    use UsesPersistenceConnection;

    protected $table = 'bigint_event_participants';

    protected function casts(): array
    {
        return [
            'event_id' => 'integer',
            'type' => ParticipantType::class,
            'rsvp' => 'boolean',
            'member' => 'array',
            'delegated_to' => 'array',
            'delegated_from' => 'array',
            'unknown_parameters' => 'array',
        ];
    }
}

final class BigIntEventAlarm extends Model
{
    use UsesPersistenceConnection;

    protected $table = 'bigint_event_alarms';

    protected function casts(): array
    {
        return ['event_id' => 'integer'];
    }
}

final class IndependentModelKeyTest extends PersistenceTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->createBigIntSchema();
        config()->set('icalendar.persistence.models', [
            'calendar' => BigIntCalendar::class,
            'event' => BigIntCalendarEvent::class,
            'participant' => BigIntEventParticipant::class,
            'alarm' => BigIntEventAlarm::class,
        ]);
    }

    protected function tearDown(): void
    {
        $schema = Schema::connection(config('icalendar.persistence.connection'));
        $schema->dropIfExists('bigint_event_alarms');
        $schema->dropIfExists('bigint_event_participants');
        $schema->dropIfExists('bigint_calendar_events');
        $schema->dropIfExists('bigint_calendars');

        parent::tearDown();
    }

    public function test_independent_models_preserve_native_integer_keys_and_foreign_keys(): void
    {
        $connection = config('icalendar.persistence.connection') ?? config('database.default');
        $this->assertSame($connection, (new BigIntCalendar)->getConnection()->getName());
        if (DB::getDriverName() !== 'sqlite') {
            $columnType = Schema::connection(config('icalendar.persistence.connection'))
                ->getColumnType('bigint_calendar_events', 'calendar_id');
            $this->assertStringContainsString('bigint', strtolower($columnType));
        }

        $calendar = app(CalendarStore::class)->create('Integer keys');
        $component = Event::build()->uid('integer-key-event')->summary('Independent')
            ->addAttendee('person@example.test')
            ->addAlarm(Alarm::build()->action(AlarmAction::Display)->trigger(Duration::minutes(-5)))
            ->get();
        $event = app(CalendarStore::class)->putEvent($calendar, $component);

        $this->assertIsInt($calendar->getKey());
        $this->assertIsInt($event->getKey());
        $this->assertSame($calendar->getKey(), $event->getAttribute('calendar_id'));
        $this->assertSame($event->getKey(), BigIntEventParticipant::query()->firstOrFail()->getAttribute('event_id'));
        $this->assertSame($event->getKey(), BigIntEventAlarm::query()->firstOrFail()->getAttribute('event_id'));
        $this->assertNotInstanceOf(Calendar::class, $calendar);

        $replacement = Event::build()->uid('integer-key-event')->summary('Replaced')->get();
        app(CalendarStore::class)->replaceEvent($event, $replacement);

        $this->assertSame('Replaced', $event->refresh()->getAttribute('summary'));
        $result = app(CalendarImporter::class)->importIcs($calendar, <<<'ICS'
BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//Test//EN
BEGIN:VEVENT
UID:imported-integer-key-event
DTSTAMP:20260817T120000Z
DTSTART:20260818T120000Z
SUMMARY:Imported
END:VEVENT
END:VCALENDAR
ICS);
        $this->assertCount(1, $result->created);
        $this->assertIsInt($result->created[0]);
        $this->assertSame(
            'integer-key-event',
            app(StoredCalendarExporter::class)->calendar($calendar)->events()[1]->uid(),
        );
    }

    private function createBigIntSchema(): void
    {
        $schema = Schema::connection(config('icalendar.persistence.connection'));

        $schema->create('bigint_calendars', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('timezone')->nullable();
            $table->string('color')->nullable();
            $table->string('source_type', 24);
            $table->boolean('enabled')->default(true);
            $table->string('owner_type')->nullable();
            $table->string('owner_id')->nullable();
            $table->longText('component_ics');
            $table->timestamps();
        });

        $schema->create('bigint_calendar_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('calendar_id')->constrained('bigint_calendars')->cascadeOnDelete();
            $table->foreignId('recurring_master_id')->nullable()->constrained('bigint_calendar_events')->nullOnDelete();
            $table->char('identity_hash', 64);
            $table->char('uid_hash', 64);
            $table->text('uid');
            $table->string('component_type', 32);
            $table->string('recurrence_id_value')->nullable();
            $table->string('recurrence_id_type', 16)->nullable();
            $table->string('recurrence_id_timezone')->nullable();
            $table->string('recurrence_range', 32)->nullable();
            $table->text('summary')->nullable();
            $table->text('description')->nullable();
            $table->text('location')->nullable();
            $table->text('url')->nullable();
            $table->string('dtstart_value')->nullable();
            $table->string('dtstart_type', 16)->nullable();
            $table->string('dtstart_timezone')->nullable();
            $table->dateTime('dtstart_utc')->nullable();
            $table->string('dtend_value')->nullable();
            $table->string('dtend_type', 16)->nullable();
            $table->string('dtend_timezone')->nullable();
            $table->dateTime('dtend_utc')->nullable();
            $table->string('duration')->nullable();
            $table->string('source_timezone')->nullable();
            $table->string('status', 32)->nullable();
            $table->string('transparency', 32)->nullable();
            $table->string('classification', 32)->nullable();
            $table->unsignedSmallInteger('priority')->nullable();
            $table->unsignedInteger('sequence')->nullable();
            $table->string('color')->nullable();
            $table->dateTime('ical_created_at')->nullable();
            $table->dateTime('ical_dtstamp')->nullable();
            $table->dateTime('ical_last_modified_at')->nullable();
            $table->text('rrule')->nullable();
            $table->boolean('is_cancelled')->default(false);
            $table->longText('component_ics');
            $table->timestamps();
        });

        $schema->create('bigint_event_participants', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_id')->constrained('bigint_calendar_events')->cascadeOnDelete();
            $table->unsignedInteger('position');
            $table->string('type', 16);
            $table->text('calendar_address');
            $table->text('common_name')->nullable();
            $table->string('role', 32)->nullable();
            $table->string('participation_status', 32)->nullable();
            $table->string('user_type', 32)->nullable();
            $table->boolean('rsvp')->nullable();
            $table->json('member')->nullable();
            $table->json('delegated_to')->nullable();
            $table->json('delegated_from')->nullable();
            $table->text('sent_by')->nullable();
            $table->text('directory')->nullable();
            $table->string('language')->nullable();
            $table->json('unknown_parameters')->nullable();
            $table->text('property_ics');
            $table->timestamps();
        });

        $schema->create('bigint_event_alarms', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_id')->constrained('bigint_calendar_events')->cascadeOnDelete();
            $table->unsignedInteger('position');
            $table->string('action', 32)->nullable();
            $table->text('trigger_value')->nullable();
            $table->string('trigger_type', 32)->nullable();
            $table->text('description')->nullable();
            $table->text('summary')->nullable();
            $table->unsignedInteger('repeat_count')->nullable();
            $table->string('repeat_duration')->nullable();
            $table->text('component_ics');
            $table->timestamps();
        });
    }
}
