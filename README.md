# vanere/laravel-icalendar

[![Latest Version](https://img.shields.io/packagist/v/vanere/laravel-icalendar.svg)](https://packagist.org/packages/vanere/laravel-icalendar)
[![Tests](https://github.com/vanere/laravel-icalendar/actions/workflows/ci.yml/badge.svg)](https://github.com/vanere/laravel-icalendar/actions/workflows/ci.yml)
[![PHP Version](https://img.shields.io/packagist/php-v/vanere/laravel-icalendar.svg)](https://packagist.org/packages/vanere/laravel-icalendar)
[![Total Downloads](https://img.shields.io/packagist/dt/vanere/laravel-icalendar.svg)](https://packagist.org/packages/vanere/laravel-icalendar)
[![License](https://img.shields.io/packagist/l/vanere/laravel-icalendar.svg)](LICENSE)

Laravel integration for [`vanere/icalendar`](https://github.com/vanere/icalendar) — build,
serve, parse, and attach iCalendar (`.ics`) feeds from your Laravel app, with first-class
Eloquent and Carbon support.

```php
use Vanere\LaravelICalendar\Facades\ICalendar;
use Vanere\ICalendar\Component\Event;

return ICalendar::response(
    ICalendar::calendar()
        ->add(Event::build()->uid('1@app.test')->summary('Launch')->starts(now()))
        ->get()
); // → a downloadable text/calendar response
```

> All the modelling — events, recurrence, time zones, parsing — lives in the core package.
> See the [core README](https://github.com/vanere/icalendar) for the full object model. This
> package is the thin Laravel glue on top.

> 📖 **New here?** The [Recipes](docs/RECIPES.md) page has short, copy-paste examples for
> feeds, Eloquent mapping, mail attachments, and more.

## Requirements

- PHP 8.3+
- Laravel 11 or 12

## Installation

```bash
composer require vanere/laravel-icalendar
```

The service provider and `ICalendar` facade are auto-discovered. Publish the config if you
want to tweak defaults:

```bash
php artisan vendor:publish --tag=icalendar-config
```

```php
// config/icalendar.php
return [
    'product_id'        => '-//' . env('APP_NAME', 'Laravel') . '//iCalendar//EN',
    'strict'            => false, // strict parse + serialize
    'include_timezones' => true,  // auto-inject VTIMEZONE on serialize
    'filename'          => 'calendar.ics',
    'uid_domain'        => null,  // defaults to APP_URL host
];
```

## The `ICalendar` facade

```php
use Vanere\LaravelICalendar\Facades\ICalendar;

ICalendar::calendar();              // CalendarBuilder, pre-stamped with config PRODID
ICalendar::event();                 // EventBuilder
ICalendar::serialize($component);   // string (.ics); auto-includes VTIMEZONE per config
ICalendar::parse($icsString);       // Calendar  (lenient, or strict per config)
ICalendar::fromModels($models);     // Calendar from a collection of mappable models
ICalendar::response($calendar);     // a downloadable CalendarResponse
```

## Mapping Eloquent models

Implement `ProvidesCalendarEvent` and use the `InteractsWithCalendar` trait. The trait gives
you a stable, deterministic UID (so re-exporting a record never duplicates it in calendar
clients) and a `toIcs()` helper. Carbon attributes flow straight into the date setters.

```php
use Illuminate\Database\Eloquent\Model;
use Vanere\ICalendar\Component\Event;
use Vanere\LaravelICalendar\Concerns\InteractsWithCalendar;
use Vanere\LaravelICalendar\Contracts\ProvidesCalendarEvent;

class Meeting extends Model implements ProvidesCalendarEvent
{
    use InteractsWithCalendar;

    protected $casts = ['starts_at' => 'datetime', 'ends_at' => 'datetime'];

    public function toCalendarEvent(): Event
    {
        return Event::build()
            ->uid($this->calendarUid())          // e.g. "Meeting-42@app.test"
            ->summary($this->title)
            ->starts($this->starts_at)            // Carbon → DateTimeInterface
            ->ends($this->ends_at)
            ->get();
    }
}
```

```php
$meeting->toIcs();                                   // single-event .ics string
ICalendar::fromModels(Meeting::today()->get());      // Calendar of many
```

## Serving a feed

`ICalendar::response()` (and the `response()->ics()` macro) return a `Responsable`
`text/calendar` download:

```php
Route::get('/calendar.ics', function () {
    return ICalendar::response(
        ICalendar::fromModels(Meeting::upcoming()->get()),
        'meetings.ics',
    );
});

// or via the macro:
return response()->ics($calendar, 'meetings.ics');
```

## Attaching to mail & notifications

`CalendarAttachment::for()` produces a standard Laravel mail `Attachment`, usable from
Mailables and notification mail messages:

```php
use Vanere\LaravelICalendar\CalendarAttachment;
use Vanere\LaravelICalendar\Facades\ICalendar;

public function toMail($notifiable): MailMessage
{
    $calendar = ICalendar::calendar()
        ->add($this->meeting->toCalendarEvent())
        ->get();

    return (new MailMessage)
        ->subject('Your meeting')
        ->line('See the attached invite.')
        ->attach(CalendarAttachment::for($calendar, 'invite.ics'));
}
```

## Artisan

```bash
php artisan icalendar:validate path/to/file.ics
# → "Valid iCalendar — 3 event(s), 4 component(s)."  (exit 0)
# → "Invalid iCalendar: …"                            (exit 1)
```

## Recurrence & time zones

These come from the core. A quick taste:

```php
use Vanere\ICalendar\Recurrence\{Recurrence, Weekday};

$event = ICalendar::event()
    ->uid('standup@app.test')
    ->starts(now())
    ->recurrence(Recurrence::weekly()->on(Weekday::Monday, Weekday::Wednesday))
    ->get();

// Override-aware expansion across a calendar:
foreach ($calendar->occurrencesBetween(now(), now()->addMonth()) as $occurrence) {
    $occurrence->start;  // Carbon-compatible DateTimeImmutable
    $occurrence->event;  // the effective event for this instance
}
```

`ICalendar::serialize()` auto-injects `VTIMEZONE` definitions for the IANA zones your events
use (toggle with `config('icalendar.include_timezones')`), so feeds are self-contained.

## Testing

```bash
composer install
vendor/bin/phpunit
```

Tests run against a real Laravel app via [Orchestra Testbench](https://github.com/orchestral/testbench).

## License

MIT
