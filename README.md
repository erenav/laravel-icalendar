# erenav/laravel-icalendar

Laravel integration and optional application-managed calendar persistence for
[`erenav/icalendar`](https://github.com/erenav/icalendar) 0.5.

The package has two independent modes:

- **ICS only:** build, parse, serialize, validate, serve, and attach calendars without a
  database.
- **Persistence enabled:** store an application's calendars, events, recurring series,
  detached overrides, participants, and alarms, then convert them to and from core objects.

External provider authentication, fetching, mappings, and synchronization remain the host
application's responsibility.

## Installation

Requirements are PHP 8.3+ and Laravel 12.

```bash
composer require erenav/laravel-icalendar
```

The service provider and `ICalendar` facade are auto-discovered. Installation does not load
database migrations.

## ICS-only use

```php
use Erenav\ICalendar\Component\Event;
use Erenav\LaravelICalendar\Facades\ICalendar;

$calendar = ICalendar::calendar()
    ->add(Event::build()->uid('launch@app.test')->summary('Launch')->starts(now()))
    ->get();

$parsed = ICalendar::parse(ICalendar::serialize($calendar));

return ICalendar::response($parsed, 'calendar.ics');
```

`CalendarAttachment::for($calendar, 'invite.ics')` creates a mail attachment,
`response()->ics($calendar)` creates a download, and
`php artisan icalendar:validate file.ics` performs a strict parse. A calendar with an iTIP
`METHOD` includes it in the attachment MIME type.

## Enabling persistence

```bash
php artisan vendor:publish --tag=icalendar-migrations
php artisan migrate
```

Then enable it in `config/icalendar.php`:

```php
'persistence' => [
    'enabled' => true,
    'load_migrations' => false,
    // ...
],
```

Setting both options to true auto-loads the package migration. This is opt-in; ICS-only
applications never need package tables.

## Local calendars and events

```php
use Erenav\LaravelICalendar\Importing\CalendarImporter;
use Erenav\LaravelICalendar\Persistence\CalendarStore;
use Erenav\LaravelICalendar\Persistence\StoredCalendarExporter;

$stored = app(CalendarStore::class)->create('Team calendar');

$result = app(CalendarImporter::class)->importIcs($stored, $uploadedIcs);
$result->counts(); // created, updated, unchanged, skipped, invalid

$calendarIcs = app(StoredCalendarExporter::class)->calendarIcs($stored);
$eventIcs = app(StoredCalendarExporter::class)->eventIcs($storedEvent);
```

`CalendarStore::createFromCore()` transactionally persists a core `Calendar`.
`putEvent()` creates or replaces one application-managed event, `replaceEvent()` updates an
existing row, and `upsertRecurringSeries()` stores a master with explicit detached
overrides. Generated occurrences are never stored.

`CalendarImporter::importCalendar()` accepts a core calendar. Preview methods select
duplicate revisions without writing. Imports are calendar-scoped and match by UID plus
RECURRENCE-ID; the core revision comparator rejects stale input. An import containing an
invalid VEVENT reports the failures and performs no writes.

The four replaceable models are:

- `Models\Calendar`
- `Models\CalendarEvent`
- `Models\EventParticipant`
- `Models\EventAlarm`

Canonical VCALENDAR, VEVENT, participant, and VALARM ICS preserves recurrence properties,
unknown RRULE parts, DATE/floating/UTC/TZID forms, embedded timezones, organizer/attendee
parameters, alarms, unknown properties, and unknown components. Normalized columns support
identity and common queries without becoming the RFC source of truth.

After loading a stored calendar as a core object, bounded recurrence expansion remains a
core operation:

```php
$core = app(StoredCalendarExporter::class)->calendar($stored);
$occurrences = $core->occurrencesBetween($from, $to);
```

## Mapping application models

`ProvidesCalendarEvent` and `InteractsWithCalendar` remain lightweight ICS projections for
an application's own models; they are independent of package persistence.

```php
class Meeting extends Model implements ProvidesCalendarEvent
{
    use InteractsWithCalendar;

    public function toCalendarEvent(): Event
    {
        return Event::build()
            ->uid($this->calendarUid())
            ->summary($this->title)
            ->starts($this->starts_at)
            ->get();
    }
}
```

## Documentation

- [Recipes](docs/RECIPES.md)
- [Configuration](docs/CONFIGURATION.md)
- [Persistence ADR](docs/PERSISTENCE.md)
- [Model extension](docs/MODELS.md)
- [Initial schema](docs/SCHEMA.md)
- [Service provider](docs/SERVICE_PROVIDER.md)

Run all supported checks with `composer check`.

## License

MIT
