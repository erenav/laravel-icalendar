<?php

declare(strict_types=1);

namespace Erenav\LaravelICalendar\Tests;

use Erenav\ICalendar\Component\Calendar;
use Erenav\ICalendar\Component\Event;
use Erenav\ICalendar\Parameter\Range;
use Erenav\ICalendar\Parser\Parser;
use Erenav\ICalendar\Recurrence\Occurrence;
use Erenav\ICalendar\Recurrence\Recurrence;
use Erenav\ICalendar\ValueType\DateTimeValue;
use Erenav\ICalendar\ValueType\Duration;
use Erenav\LaravelICalendar\Persistence\CalendarStore;
use Erenav\LaravelICalendar\Persistence\StoredCalendarExporter;

final class RecurrenceRoundTripTest extends PersistenceTestCase
{
    public function test_overrides_moved_into_and_out_of_a_window_keep_effective_start_semantics(): void
    {
        $slotOutside = DateTimeValue::utc(new \DateTimeImmutable('2026-08-14 09:00:00 UTC'));
        $slotInside = DateTimeValue::utc(new \DateTimeImmutable('2026-08-15 09:00:00 UTC'));
        $core = Calendar::build()
            ->add(Event::build()->uid('window')->starts($slotOutside)->recurrence(Recurrence::daily()->times(2)))
            ->add(Event::build()->uid('window')->recurrenceId($slotOutside)
                ->starts(DateTimeValue::utc(new \DateTimeImmutable('2026-08-15 10:00:00 UTC')))->summary('Moved in'))
            ->add(Event::build()->uid('window')->recurrenceId($slotInside)
                ->starts(DateTimeValue::utc(new \DateTimeImmutable('2026-08-14 10:00:00 UTC')))->summary('Moved out'))
            ->get();

        $stored = app(CalendarStore::class)->createFromCore($core);
        $fresh = $stored::query()->whereKey($stored->getKey())->firstOrFail();
        $loaded = app(StoredCalendarExporter::class)->calendar($fresh);
        $from = new \DateTimeImmutable('2026-08-15 00:00:00 UTC');
        $to = new \DateTimeImmutable('2026-08-15 23:59:59 UTC');

        $this->assertSame($this->occurrences($core->occurrencesBetween($from, $to)), $this->occurrences($loaded->occurrencesBetween($from, $to)));
        $this->assertSame('Moved in', $loaded->occurrencesBetween($from, $to)[0]->event->summary());
        $this->assertCount(1, $loaded->occurrencesBetween($from, $to));
    }

    public function test_range_and_single_override_expansion_is_unchanged_by_database_round_trip(): void
    {
        $dayOne = DateTimeValue::utc(new \DateTimeImmutable('2026-08-15 09:00:00 UTC'));
        $dayTwo = DateTimeValue::utc(new \DateTimeImmutable('2026-08-16 09:00:00 UTC'));
        $dayThree = DateTimeValue::utc(new \DateTimeImmutable('2026-08-17 09:00:00 UTC'));
        $core = Calendar::build()
            ->prodId('-//Range Test//EN')
            ->add(
                Event::build()->uid('range@example.test')->starts($dayOne)
                    ->lasting(Duration::hours(1))->summary('Master')
                    ->recurrence(Recurrence::daily()->times(3)),
            )
            ->add(
                Event::build()->uid('range@example.test')
                    ->recurrenceId($dayTwo, Range::ThisAndFuture)
                    ->starts(DateTimeValue::utc(new \DateTimeImmutable('2026-08-16 11:00:00 UTC')))
                    ->summary('Range moved'),
            )
            ->add(
                Event::build()->uid('range@example.test')->recurrenceId($dayThree)
                    ->starts(DateTimeValue::utc(new \DateTimeImmutable('2026-08-14 08:00:00 UTC')))
                    ->summary('Moved out'),
            )
            ->get();

        $stored = app(CalendarStore::class)->createFromCore($core);
        $loaded = app(StoredCalendarExporter::class)->calendar($stored);
        $from = new \DateTimeImmutable('2026-08-15 00:00:00 UTC');
        $to = new \DateTimeImmutable('2026-08-20 00:00:00 UTC');

        $this->assertSame(
            $this->occurrences($core->occurrencesBetween($from, $to)),
            $this->occurrences($loaded->occurrencesBetween($from, $to)),
        );
        $this->assertCount(2, $loaded->occurrencesBetween($from, $to));

        $eventIcs = app(StoredCalendarExporter::class)->eventIcs($stored->events()->firstOrFail());
        $this->assertCount(1, Parser::lenient()->parseCalendar($eventIcs)->events());
    }

    /** @param list<Occurrence> $occurrences */
    private function occurrences(array $occurrences): array
    {
        return array_map(static fn (Occurrence $occurrence): array => [
            $occurrence->start->format('c'),
            $occurrence->recurrenceId->format('c'),
            $occurrence->event->summary(),
            $occurrence->isOverride,
        ], $occurrences);
    }
}
