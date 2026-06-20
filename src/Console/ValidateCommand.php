<?php

declare(strict_types=1);

namespace Vanere\LaravelICalendar\Console;

use Illuminate\Console\Command;
use Vanere\ICalendar\Exception\ICalendarException;
use Vanere\ICalendar\Parser\Parser;

final class ValidateCommand extends Command
{
    protected $signature = 'icalendar:validate {path : Path to the .ics file to validate}';

    protected $description = 'Validate an iCalendar (.ics) file by strict-parsing it';

    public function handle(): int
    {
        $path = (string) $this->argument('path');

        if (! is_file($path)) {
            $this->error("File not found: {$path}");

            return self::FAILURE;
        }

        try {
            $calendar = Parser::strict()->parseCalendar((string) file_get_contents($path));
        } catch (ICalendarException $e) {
            $this->error('Invalid iCalendar: ' . $e->getMessage());

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
