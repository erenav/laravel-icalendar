<?php

declare(strict_types=1);

namespace Erenav\LaravelICalendar\Tests;

use Erenav\LaravelICalendar\ICalendarManager;
use Erenav\LaravelICalendar\ICalendarServiceProvider;
use Erenav\LaravelICalendar\Persistence\CalendarStore;
use Erenav\LaravelICalendar\Persistence\StoredCalendarExporter;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

final class PersistenceBootTest extends TestCase
{
    public function test_documented_publication_tags_are_registered(): void
    {
        $config = ServiceProvider::pathsToPublish(ICalendarServiceProvider::class, 'icalendar-config');
        $migrations = ServiceProvider::pathsToPublish(ICalendarServiceProvider::class, 'icalendar-migrations');

        $this->assertContains(realpath(__DIR__.'/../config/icalendar.php'), array_map(realpath(...), array_keys($config)));
        $this->assertContains($this->app->configPath('icalendar.php'), array_values($config));
        $this->assertContains(
            realpath(__DIR__.'/../database/migrations/2026_08_15_000000_create_icalendar_persistence_tables.php'),
            array_map(realpath(...), array_keys($migrations)),
        );
        $this->assertContains(
            $this->app->databasePath('migrations/2026_08_15_000000_create_icalendar_persistence_tables.php'),
            array_values($migrations),
        );
    }

    public function test_cached_configuration_preserves_manager_and_persistence_service_resolution(): void
    {
        $configuration = require __DIR__.'/../config/icalendar.php';
        $path = tempnam(sys_get_temp_dir(), 'icalendar-config-');
        $this->assertIsString($path);
        file_put_contents($path, '<?php return '.var_export($configuration, true).';');

        try {
            $cached = require $path;
        } finally {
            @unlink($path);
        }

        $this->assertIsArray($cached);
        $this->app['config']->set('icalendar', $cached);
        $this->app->forgetInstance(ICalendarManager::class);

        $this->assertInstanceOf(ICalendarManager::class, $this->app->make(ICalendarManager::class));
        $this->assertInstanceOf(CalendarStore::class, $this->app->make(CalendarStore::class));
        $this->assertInstanceOf(StoredCalendarExporter::class, $this->app->make(StoredCalendarExporter::class));
    }

    public function test_persistence_is_disabled_and_migrations_are_not_loaded_by_default(): void
    {
        $this->assertFalse((bool) config('icalendar.persistence.enabled'));
        $this->assertFalse((bool) config('icalendar.persistence.load_migrations'));
        $this->assertFalse(Schema::hasTable('ical_calendars'));
    }

    public function test_persistence_services_fail_clearly_while_disabled(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('persistence is disabled');

        app(CalendarStore::class)->create('Disabled');
    }

    public function test_every_public_export_path_checks_the_gate_before_querying_tables(): void
    {
        $record = new class extends Model
        {
            protected $table = 'table_that_must_not_be_queried';
        };
        $record->setAttribute('calendar_id', 'missing');

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('persistence is disabled');
        app(StoredCalendarExporter::class)->eventCalendar($record);
    }
}
