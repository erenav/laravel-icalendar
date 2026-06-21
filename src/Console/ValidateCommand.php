<?php

declare(strict_types=1);

namespace Erenav\LaravelICalendar\Console;

use Erenav\ICalendar\Exception\ICalendarException;
use Erenav\ICalendar\Parser\Parser;
use Illuminate\Console\Command;

final class ValidateCommand extends Command
{
    protected $signature = 'icalendar:validate {path : Path to the .ics file to validate}';

    protected $description = 'Validate an iCalendar (.ics) file by strict-parsing it';

    public function handle(): int
    {
        $path = $this->argument('path');

        if (! is_string($path)) {
            $this->error('A file path is required.');

            return self::FAILURE;
        }

        if (! is_file($path)) {
            $this->error("File not found: {$path}");

            return self::FAILURE;
        }

        try {
            $calendar = Parser::strict()->parseCalendar((string) file_get_contents($path));
        } catch (ICalendarException $e) {
            $this->error('Invalid iCalendar: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info(sprintf(
            'Valid iCalendar — %d event(s), %d component(s).',
            count($calendar->events()),
            count($calendar->components()),
        ));

        return self::SUCCESS;
    }
}
