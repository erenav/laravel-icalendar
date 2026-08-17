<?php

declare(strict_types=1);

namespace Erenav\LaravelICalendar\Tests;

use Erenav\ICalendar\Component\Alarm;
use Erenav\ICalendar\Component\Event;
use Erenav\ICalendar\Property\AlarmAction;
use Erenav\ICalendar\ValueType\Duration;
use Erenav\LaravelICalendar\Models\Calendar;
use Erenav\LaravelICalendar\Models\CalendarEvent;
use Erenav\LaravelICalendar\Models\EventAlarm;
use Erenav\LaravelICalendar\Models\EventParticipant;
use Erenav\LaravelICalendar\Persistence\CalendarStore;
use Erenav\LaravelICalendar\Persistence\StoredCalendarExporter;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

final class ReplacementCalendar extends Calendar {}

final class ReplacementCalendarEvent extends CalendarEvent {}

final class ReplacementEventParticipant extends EventParticipant {}

final class ReplacementEventAlarm extends EventAlarm {}

final class CalendarOwnerPrincipal extends Model
{
    protected $table = 'calendar_owner_principals';

    public $timestamps = false;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];
}

final class ModelExtensionTest extends PersistenceTestCase
{
    public function test_configured_model_replacement_and_optional_ownership(): void
    {
        config()->set('icalendar.persistence.models.calendar', ReplacementCalendar::class);
        config()->set('icalendar.persistence.models.event', ReplacementCalendarEvent::class);
        $calendar = app(CalendarStore::class)->create('Replacement');
        $this->assertInstanceOf(ReplacementCalendar::class, $calendar);
        $event = app(CalendarStore::class)->putEvent($calendar, Event::build()->uid('replacement')->get());
        $this->assertInstanceOf(ReplacementCalendarEvent::class, $event);
        $this->assertInstanceOf(ReplacementCalendarEvent::class, $calendar->events()->firstOrFail());

        $this->expectException(\LogicException::class);
        app(CalendarStore::class)->assignOwner($calendar, 'account', '42');
    }

    public function test_owner_identifiers_are_nullable_and_can_be_enabled_without_a_user_model(): void
    {
        config()->set('icalendar.persistence.owner.enabled', true);
        $calendar = app(CalendarStore::class)->create('Owned');
        app(CalendarStore::class)->assignOwner($calendar, 'external-principal', 'principal-7');

        $this->assertSame('external-principal', $calendar->refresh()->getAttribute('owner_type'));
        $this->assertSame('principal-7', $calendar->getAttribute('owner_id'));
    }

    public function test_optional_owner_relationship_resolves_an_eloquent_principal(): void
    {
        Schema::create('calendar_owner_principals', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('name');
        });
        config()->set('icalendar.persistence.owner.enabled', true);
        $owner = CalendarOwnerPrincipal::query()->create(['id' => 'owner-1', 'name' => 'Calendar owner']);
        $calendar = app(CalendarStore::class)->create('Owned relation');

        app(CalendarStore::class)->assignOwner($calendar, CalendarOwnerPrincipal::class, (string) $owner->getKey());

        $fresh = $calendar::query()->whereKey($calendar->getKey())->firstOrFail();
        $this->assertInstanceOf(CalendarOwnerPrincipal::class, $fresh->owner()->firstOrFail());
        $this->assertSame('Calendar owner', $fresh->owner()->firstOrFail()->getAttribute('name'));
    }

    public function test_all_configured_models_are_used_by_services_and_relationships(): void
    {
        config()->set('icalendar.persistence.models', [
            'calendar' => ReplacementCalendar::class,
            'event' => ReplacementCalendarEvent::class,
            'participant' => ReplacementEventParticipant::class,
            'alarm' => ReplacementEventAlarm::class,
        ]);
        $calendar = app(CalendarStore::class)->create('All replacements');
        $event = Event::build()->uid('all-replacements')
            ->addAttendee('person@example.test')
            ->addAlarm(Alarm::build()->action(AlarmAction::Display)->trigger(Duration::minutes(-5)))
            ->get();
        $storedEvent = app(CalendarStore::class)->putEvent($calendar, $event);

        $this->assertInstanceOf(ReplacementCalendarEvent::class, $storedEvent);
        $this->assertInstanceOf(ReplacementEventParticipant::class, $storedEvent->participants()->firstOrFail());
        $this->assertInstanceOf(ReplacementEventAlarm::class, $storedEvent->alarms()->firstOrFail());
        $freshCalendar = ReplacementCalendar::query()->whereKey($calendar->getKey())->firstOrFail();
        $exported = app(StoredCalendarExporter::class)->calendar($freshCalendar);
        $this->assertSame('all-replacements', $exported->events()[0]->uid());
    }
}
