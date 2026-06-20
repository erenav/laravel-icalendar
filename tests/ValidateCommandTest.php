<?php

declare(strict_types=1);

namespace Vanere\LaravelICalendar\Tests;

use DateTimeImmutable;
use DateTimeZone;
use Vanere\ICalendar\Component\Event;
use Vanere\ICalendar\ValueType\DateTimeValue;
use Vanere\LaravelICalendar\Facades\ICalendar;

final class ValidateCommandTest extends TestCase
{
    private function tempFile(string $contents): string
    {
        $path = tempnam(sys_get_temp_dir(), 'ics');
        file_put_contents($path, $contents);

        return $path;
    }

    public function test_valid_file_passes(): void
    {
        $calendar = ICalendar::calendar()
            ->add(
                Event::build()
                    ->uid('1@test')
                    ->timestamp(DateTimeValue::utc(new DateTimeImmutable('2026-06-20 12:00:00', new DateTimeZone('UTC'))))
                    ->summary('Hi'),
            )
            ->get();

        $path = $this->tempFile(ICalendar::serialize($calendar));

        $this->artisan('icalendar:validate', ['path' => $path])
            ->expectsOutputToContain('Valid iCalendar')
            ->assertExitCode(0);

        @unlink($path);
    }

    public function test_invalid_file_fails(): void
    {
        $path = $this->tempFile('this is not an iCalendar file');

        $this->artisan('icalendar:validate', ['path' => $path])->assertExitCode(1);

        @unlink($path);
    }

    public function test_missing_file_fails(): void
    {
        $this->artisan('icalendar:validate', ['path' => '/no/such/file.ics'])->assertExitCode(1);
    }
}
