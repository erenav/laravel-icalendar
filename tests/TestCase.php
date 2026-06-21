<?php

declare(strict_types=1);

namespace Erenav\LaravelICalendar\Tests;

use Erenav\LaravelICalendar\Facades\ICalendar;
use Erenav\LaravelICalendar\ICalendarServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [ICalendarServiceProvider::class];
    }

    protected function getPackageAliases($app): array
    {
        return ['ICalendar' => ICalendar::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('icalendar.product_id', '-//Test//EN');
        $app['config']->set('app.url', 'https://example.test');
    }
}
