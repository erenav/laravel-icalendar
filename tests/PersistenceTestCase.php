<?php

declare(strict_types=1);

namespace Erenav\LaravelICalendar\Tests;

abstract class PersistenceTestCase extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $driver = getenv('ICALENDAR_TEST_DB_DRIVER') ?: 'sqlite';
        if ($driver === 'sqlite') {
            $app['config']->set('database.default', 'sqlite');
            $app['config']->set('database.connections.sqlite', [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
                'foreign_key_constraints' => true,
            ]);
        } else {
            $app['config']->set('database.default', 'icalendar_audit');
            $connection = [
                'driver' => $driver,
                'host' => getenv('ICALENDAR_TEST_DB_HOST') ?: '127.0.0.1',
                'port' => getenv('ICALENDAR_TEST_DB_PORT') ?: ($driver === 'mysql' ? '3306' : '5432'),
                'database' => getenv('ICALENDAR_TEST_DB_DATABASE') ?: 'icalendar_audit',
                'username' => getenv('ICALENDAR_TEST_DB_USERNAME') ?: 'icalendar_audit',
                'password' => getenv('ICALENDAR_TEST_DB_PASSWORD') ?: 'icalendar_audit',
                'charset' => $driver === 'mysql' ? 'utf8mb4' : 'utf8',
                'prefix' => '',
                'prefix_indexes' => true,
            ];
            if ($driver === 'mysql') {
                $connection['collation'] = 'utf8mb4_unicode_ci';
            }
            $app['config']->set('database.connections.icalendar_audit', $connection);
            $app['config']->set('icalendar.persistence.connection', 'icalendar_audit');
        }
        $app['config']->set('icalendar.persistence.enabled', true);
        $app['config']->set('icalendar.persistence.load_migrations', true);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('migrate', ['--no-interaction' => true])->run();
    }
}
