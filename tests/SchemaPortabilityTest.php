<?php

declare(strict_types=1);

namespace Erenav\LaravelICalendar\Tests;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class SchemaPortabilityTest extends TestCase
{
    public function test_migration_avoids_engine_specific_schema_features(): void
    {
        $migration = file_get_contents(__DIR__.'/../database/migrations/2026_08_15_000000_create_icalendar_persistence_tables.php');
        $this->assertIsString($migration);

        foreach (['->enum(', 'generatedAs(', 'storedAs(', 'virtualAs(', 'DB::statement', 'fullText('] as $engineSpecific) {
            $this->assertStringNotContainsString($engineSpecific, $migration);
        }
        $this->assertStringContainsString("char('identity_hash', 64)", $migration);
    }

    public function test_migration_compiles_with_mysql_and_postgresql_schema_grammars(): void
    {
        $original = config('database.default');
        foreach (['mysql', 'pgsql'] as $driver) {
            $name = 'audit_'.$driver;
            config()->set("database.connections.{$name}", [
                'driver' => $driver,
                'database' => 'icalendar_audit',
                'host' => '127.0.0.1',
                'username' => 'audit',
                'password' => '',
                'prefix' => '',
            ]);
            config()->set('database.default', $name);
            $migration = require __DIR__.'/../database/migrations/2026_08_15_000000_create_icalendar_persistence_tables.php';
            $queries = DB::connection($name)->pretend(static fn () => $migration->up());

            $this->assertGreaterThanOrEqual(20, count($queries), $driver);
            $sql = implode("\n", array_column($queries, 'query'));
            $this->assertStringContainsString('ical_calendar_events', $sql);
            DB::purge($name);
        }
        config()->set('database.default', $original);
    }

    public function test_migration_uses_the_configured_persistence_connection_for_install_and_rollback(): void
    {
        config()->set('database.connections.audit_application', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
        config()->set('database.connections.audit_calendar', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
        config()->set('database.default', 'audit_application');
        config()->set('icalendar.persistence.connection', 'audit_calendar');
        $migration = require __DIR__.'/../database/migrations/2026_08_15_000000_create_icalendar_persistence_tables.php';

        $migration->up();

        $this->assertFalse(Schema::connection('audit_application')->hasTable('ical_calendars'));
        $this->assertTrue(Schema::connection('audit_calendar')->hasTable('ical_calendars'));
        $this->assertTrue(Schema::connection('audit_calendar')->hasTable('ical_calendar_events'));

        $migration->down();

        $this->assertFalse(Schema::connection('audit_calendar')->hasTable('ical_calendars'));
        $this->assertFalse(Schema::connection('audit_calendar')->hasTable('ical_calendar_events'));
    }
}
