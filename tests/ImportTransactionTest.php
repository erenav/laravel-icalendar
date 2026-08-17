<?php

declare(strict_types=1);

namespace Erenav\LaravelICalendar\Tests;

use Erenav\ICalendar\Component\Alarm;
use Erenav\ICalendar\Component\Calendar;
use Erenav\ICalendar\Component\Event;
use Erenav\ICalendar\Parser\Parser;
use Erenav\ICalendar\Property\AlarmAction;
use Erenav\ICalendar\ValueType\Duration;
use Erenav\LaravelICalendar\Models\Calendar as StoredCalendar;
use Erenav\LaravelICalendar\Models\CalendarEvent;
use Erenav\LaravelICalendar\Models\EventAlarm;
use Erenav\LaravelICalendar\Persistence\CalendarStore;

final class FailingCalendarEvent extends CalendarEvent
{
    public function save(array $options = []): bool
    {
        if ($this->getAttribute('summary') === 'explode') {
            throw new \RuntimeException('Injected event write failure.');
        }

        return parent::save($options);
    }
}

final class FailingEventAlarm extends EventAlarm
{
    public function save(array $options = []): bool
    {
        if ($this->getAttribute('summary') === 'explode') {
            throw new \RuntimeException('Injected alarm write failure.');
        }

        return parent::save($options);
    }
}

final class ImportTransactionTest extends PersistenceTestCase
{
    public function test_invalid_components_report_failures_without_persisting_any_part_of_the_import(): void
    {
        $store = app(CalendarStore::class);
        $stored = $store->create('Before');
        $store->putEvent($stored, Event::build()->uid('existing')->summary('Existing')->get());
        $beforeEnvelope = $stored->refresh()->getAttribute('component_ics');
        $invalidSource = Parser::lenient()->parseCalendar(<<<'ICS'
BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//Invalid import//EN
NAME:Must not persist
BEGIN:VEVENT
UID:valid-sibling
SUMMARY:Must not persist
END:VEVENT
BEGIN:VEVENT
UID:invalid-revision
SEQUENCE:-1
SUMMARY:Invalid
END:VEVENT
END:VCALENDAR
ICS);

        $result = $store->import($stored, $invalidSource);

        $this->assertSame(1, $result->counts()['invalid']);
        $this->assertSame(0, $result->counts()['created']);
        $this->assertSame('Before', $stored->refresh()->getAttribute('name'));
        $this->assertSame($beforeEnvelope, $stored->getAttribute('component_ics'));
        $this->assertSame(['existing'], CalendarEvent::query()->pluck('uid')->all());
    }

    public function test_create_from_core_rolls_back_the_calendar_when_validation_fails(): void
    {
        $invalidSource = Parser::lenient()->parseCalendar(<<<'ICS'
BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//Invalid create//EN
BEGIN:VEVENT
UID:invalid-create
SEQUENCE:-1
END:VEVENT
END:VCALENDAR
ICS);

        try {
            app(CalendarStore::class)->createFromCore($invalidSource);
            $this->fail('An invalid core calendar must not create a partial calendar.');
        } catch (\InvalidArgumentException $exception) {
            $this->assertStringContainsString('SEQUENCE', $exception->getMessage());
        }

        $this->assertSame(0, StoredCalendar::query()->count());
        $this->assertSame(0, CalendarEvent::query()->count());
    }

    public function test_exception_rolls_back_calendar_envelope_and_every_related_event_write(): void
    {
        config()->set('icalendar.persistence.models.event', FailingCalendarEvent::class);
        $stored = app(CalendarStore::class)->create('Before');
        $beforeIcs = $stored->getAttribute('component_ics');
        $source = Calendar::build()->prodId('-//Rollback//EN')->name('After')
            ->add(Event::build()->uid('first')->summary('first'))
            ->add(Event::build()->uid('second')->summary('explode'))
            ->get();

        try {
            app(CalendarStore::class)->import($stored, $source);
            $this->fail('The injected write failure should abort the import.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Injected event write failure.', $exception->getMessage());
        }

        $this->assertSame('Before', $stored->refresh()->getAttribute('name'));
        $this->assertSame($beforeIcs, $stored->getAttribute('component_ics'));
        $this->assertSame(0, FailingCalendarEvent::query()->count());
    }

    public function test_child_reconciliation_failure_restores_the_event_and_all_original_children(): void
    {
        $store = app(CalendarStore::class);
        $calendar = $store->create('Before');
        $original = Event::build()->uid('children')->sequence(1)->summary('Original')
            ->addAttendee('original@example.test')
            ->addAlarm(Alarm::build()->action(AlarmAction::Display)->trigger(Duration::minutes(-5))->summary('Original alarm'))
            ->get();
        $store->import($calendar, Calendar::build()->name('Before')->add($original)->get());
        $beforeEnvelope = $calendar->refresh()->getAttribute('component_ics');
        config()->set('icalendar.persistence.models.alarm', FailingEventAlarm::class);

        $replacement = Event::build()->uid('children')->sequence(2)->summary('Replacement')
            ->addAttendee('replacement@example.test')
            ->addAlarm(Alarm::build()->action(AlarmAction::Display)->trigger(Duration::minutes(-10))->summary('First replacement'))
            ->addAlarm(Alarm::build()->action(AlarmAction::Display)->trigger(Duration::minutes(-15))->summary('explode'))
            ->get();

        try {
            $store->import($calendar, Calendar::build()->name('After')->add($replacement)->get());
            $this->fail('The injected child failure should abort the complete import.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Injected alarm write failure.', $exception->getMessage());
        }

        $freshEvent = CalendarEvent::query()->where('uid', 'children')->firstOrFail();
        $this->assertSame('Before', $calendar->refresh()->getAttribute('name'));
        $this->assertSame($beforeEnvelope, $calendar->getAttribute('component_ics'));
        $this->assertSame('Original', $freshEvent->getAttribute('summary'));
        $this->assertSame('mailto:original@example.test', $freshEvent->participants()->firstOrFail()->getAttribute('calendar_address'));
        $this->assertSame('Original alarm', $freshEvent->alarms()->firstOrFail()->getAttribute('summary'));
        $this->assertSame(1, $freshEvent->alarms()->count());
    }
}
