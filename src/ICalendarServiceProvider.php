<?php

declare(strict_types=1);

namespace Vanere\LaravelICalendar;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Routing\ResponseFactory;
use Illuminate\Support\ServiceProvider;
use Vanere\ICalendar\Component\Component;
use Vanere\LaravelICalendar\Console\ValidateCommand;

final class ICalendarServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/icalendar.php', 'icalendar');

        $this->app->singleton(ICalendarManager::class, static function (Application $app): ICalendarManager {
            $config = $app->make(Repository::class)->get('icalendar', []);

            return new ICalendarManager(is_array($config) ? $config : []);
        });

        $this->app->alias(ICalendarManager::class, 'icalendar');
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/icalendar.php' => $this->app->configPath('icalendar.php'),
            ], 'icalendar-config');

            $this->commands([ValidateCommand::class]);
        }

        $this->registerResponseMacro();
    }

    /** Adds `response()->ics($component, $filename)`. */
    private function registerResponseMacro(): void
    {
        if (class_exists(ResponseFactory::class) && ! ResponseFactory::hasMacro('ics')) {
            ResponseFactory::macro('ics', function (Component $component, ?string $filename = null): CalendarResponse {
                return app(ICalendarManager::class)->response($component, $filename);
            });
        }
    }
}
