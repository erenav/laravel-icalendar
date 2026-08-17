<?php

declare(strict_types=1);

namespace Erenav\LaravelICalendar\Tests;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class MigrationTest extends PersistenceTestCase
{
    public function test_fresh_migration_and_rollback(): void
    {
        $tables = [
            'ical_calendars',
            'ical_calendar_events',
            'ical_event_participants',
            'ical_event_alarms',
        ];
        foreach ($tables as $table) {
            $this->assertTrue(Schema::hasTable($table), $table);
        }
        if (DB::getDriverName() === 'sqlite') {
            $eventIndexes = array_column(DB::select('PRAGMA index_list(ical_calendar_events)'), 'name');
            $this->assertContains('ical_event_identity_uq', $eventIndexes);
            $this->assertContains('ical_event_master_rid_idx', $eventIndexes);
            $eventForeignKeys = DB::select('PRAGMA foreign_key_list(ical_calendar_events)');
            $actions = [];
            foreach ($eventForeignKeys as $foreignKey) {
                $actions[$foreignKey->from] = [$foreignKey->table, $foreignKey->on_delete];
            }
            $this->assertSame(['ical_calendars', 'CASCADE'], $actions['calendar_id']);
            $this->assertSame(['ical_calendar_events', 'SET NULL'], $actions['recurring_master_id']);
            $this->assertSame(
                ['event_id' => ['ical_calendar_events', 'CASCADE']],
                $this->foreignKeys('ical_event_participants'),
            );
            $this->assertSame(
                ['event_id' => ['ical_calendar_events', 'CASCADE']],
                $this->foreignKeys('ical_event_alarms'),
            );
            foreach ([
                'ical_event_participants' => 'ical_participant_position_uq',
                'ical_event_alarms' => 'ical_alarm_position_uq',
            ] as $table => $index) {
                $indexes = array_column(DB::select("PRAGMA index_list({$table})"), 'name');
                $this->assertContains($index, $indexes);
            }

            $eventColumns = collect(DB::select('PRAGMA table_info(ical_calendar_events)'))->keyBy('name');
            $this->assertSame(0, $eventColumns['recurring_master_id']->notnull);
            $this->assertSame(0, $eventColumns['dtend_value']->notnull);
            $this->assertSame(0, $eventColumns['duration']->notnull);
            $this->assertSame(1, $eventColumns['component_ics']->notnull);
            $calendarColumns = collect(DB::select('PRAGMA table_info(ical_calendars)'))->keyBy('name');
            $this->assertSame(0, $calendarColumns['owner_type']->notnull);
            $this->assertSame(0, $calendarColumns['owner_id']->notnull);
            $this->assertSame(1, $calendarColumns['component_ics']->notnull);
        }

        $this->artisan('migrate:rollback', ['--no-interaction' => true])->assertSuccessful();

        foreach ($tables as $table) {
            $this->assertFalse(Schema::hasTable($table), $table);
        }
    }

    /** @return array<string, array{string, string}> */
    private function foreignKeys(string $table): array
    {
        $keys = [];
        foreach (DB::select("PRAGMA foreign_key_list({$table})") as $foreignKey) {
            $keys[$foreignKey->from] = [$foreignKey->table, $foreignKey->on_delete];
        }
        ksort($keys);

        return $keys;
    }
}
