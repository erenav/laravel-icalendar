<?php

declare(strict_types=1);

namespace Vanere\LaravelICalendar\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Vanere\LaravelICalendar\Facades\ICalendar;
use Vanere\LaravelICalendar\ICalendarServiceProvider;

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
