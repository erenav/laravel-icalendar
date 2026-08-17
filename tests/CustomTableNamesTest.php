<?php

declare(strict_types=1);

namespace Erenav\LaravelICalendar\Tests;

use Erenav\ICalendar\Component\Alarm;
use Erenav\ICalendar\Component\Event;
use Erenav\ICalendar\Property\AlarmAction;
use Erenav\ICalendar\ValueType\Duration;
use Erenav\LaravelICalendar\Models\Calendar;
use Erenav\LaravelICalendar\Models\CalendarEvent;
use Erenav\LaravelICalendar\Models\EventAlarm;
use Erenav\LaravelICalendar\Models\EventParticipant;
use Erenav\LaravelICalendar\Persistence\CalendarStore;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class CustomTableNamesTest extends PersistenceTestCase
{
    /** @var array<string, string> */
    private const TABLES = [
        'calendar' => 'custom_calendars',
        'event' => 'custom_calendar_events',
        'participant' => 'custom_event_participants',
        'alarm' => 'custom_event_alarms',
    ];

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('icalendar.persistence.tables', self::TABLES);
    }

    public function test_models_and_persistence_use_configured_table_names(): void
    {
        $this->assertSame(self::TABLES['calendar'], (new Calendar)->getTable());
        $this->assertSame(self::TABLES['event'], (new CalendarEvent)->getTable());
        $this->assertSame(self::TABLES['participant'], (new EventParticipant)->getTable());
        $this->assertSame(self::TABLES['alarm'], (new EventAlarm)->getTable());

        foreach (self::TABLES as $table) {
            $this->assertTrue(Schema::hasTable($table));
        }

        $calendar = app(CalendarStore::class)->create('Custom tables');
        $event = Event::build()->uid('custom-tables')
            ->addAttendee('person@example.test')
            ->addAlarm(Alarm::build()->action(AlarmAction::Display)->trigger(Duration::minutes(-5)))
            ->get();
        $stored = app(CalendarStore::class)->putEvent($calendar, $event);

        $this->assertSame(1, Calendar::query()->count());
        $this->assertSame(1, CalendarEvent::query()->count());
        $this->assertSame(1, $stored->participants()->count());
        $this->assertSame(1, $stored->alarms()->count());
    }

    public function test_foreign_keys_and_rollback_use_configured_table_names(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            $eventForeignKeys = $this->foreignKeys(self::TABLES['event']);

            $this->assertSame(self::TABLES['calendar'], $eventForeignKeys['calendar_id'][0]);
            $this->assertSame(self::TABLES['event'], $eventForeignKeys['recurring_master_id'][0]);
            $this->assertSame(self::TABLES['event'], $this->foreignKeys(self::TABLES['participant'])['event_id'][0]);
            $this->assertSame(self::TABLES['event'], $this->foreignKeys(self::TABLES['alarm'])['event_id'][0]);
        }

        $this->artisan('migrate:rollback', ['--no-interaction' => true])->assertSuccessful();

        foreach (self::TABLES as $table) {
            $this->assertFalse(Schema::hasTable($table));
        }
    }

    /** @return array<string, array{string, string}> */
    private function foreignKeys(string $table): array
    {
        $keys = [];
        foreach (DB::select("PRAGMA foreign_key_list({$table})") as $foreignKey) {
            $keys[$foreignKey->from] = [$foreignKey->table, $foreignKey->on_delete];
        }

        return $keys;
    }
}
