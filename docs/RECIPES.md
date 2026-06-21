# Recipes (Laravel)

Copy-paste examples for common Laravel tasks. Everything goes through the `ICalendar`
facade or the small set of helper classes. For the calendar/event modelling itself, see
the [core recipes](https://github.com/erenav/icalendar/blob/main/docs/RECIPES.md).

```php
use Erenav\LaravelICalendar\Facades\ICalendar;
use Erenav\ICalendar\Component\Event;
```

---

## Serve a calendar feed from a route

```php
Route::get('/calendar.ics', function () {
    $calendar = ICalendar::calendar()
        ->add(Event::build()->uid('1@app.test')->summary('Launch')->starts(now()))
        ->get();

    return ICalendar::response($calendar, 'events.ics');   // downloadable text/calendar
});
```

Or with the response macro:

```php
return response()->ics($calendar, 'events.ics');
```

## Turn an Eloquent model into an event

```php
use Illuminate\Database\Eloquent\Model;
use Erenav\ICalendar\Component\Event;
use Erenav\LaravelICalendar\Concerns\InteractsWithCalendar;
use Erenav\LaravelICalendar\Contracts\ProvidesCalendarEvent;

class Meeting extends Model implements ProvidesCalendarEvent
{
    use InteractsWithCalendar;

    protected $casts = ['starts_at' => 'datetime', 'ends_at' => 'datetime'];

    public function toCalendarEvent(): Event
    {
        return Event::build()
            ->uid($this->calendarUid())     // stable id, e.g. "Meeting-42@app.test"
            ->summary($this->title)
            ->starts($this->starts_at)       // Carbon works directly
            ->ends($this->ends_at)
            ->get();
    }
}
```

## Serve many models as a feed

```php
Route::get('/meetings.ics', fn () => ICalendar::response(
    ICalendar::fromModels(Meeting::upcoming()->get())
));
```

## One model as a downloadable file

```php
return ICalendar::response(
    ICalendar::calendar()->add($meeting->toCalendarEvent())->get(),
    'meeting.ics',
);

// or just the string:
$ics = $meeting->toIcs();
```

## Attach an invite to a notification

```php
use Erenav\LaravelICalendar\CalendarAttachment;
use Erenav\LaravelICalendar\Facades\ICalendar;

public function toMail($notifiable): MailMessage
{
    $calendar = ICalendar::calendar()->add($this->meeting->toCalendarEvent())->get();

    return (new MailMessage)
        ->subject('Your meeting')
        ->line('See the attached invite.')
        ->attach(CalendarAttachment::for($calendar, 'invite.ics'));
}
```

## Parse an uploaded `.ics`

```php
$calendar = ICalendar::parse($request->file('ics')->get());

foreach ($calendar->events() as $event) {
    // $event->summary(), $event->start(), $event->attendees(), ...
}
```

## Validate an `.ics` file from the CLI

```bash
php artisan icalendar:validate storage/app/feed.ics
```

## Configure defaults

```bash
php artisan vendor:publish --tag=icalendar-config
```

```php
// config/icalendar.php
return [
    'product_id'        => '-//'.env('APP_NAME', 'Laravel').'//iCalendar//EN',
    'strict'            => false, // throw on invalid input/output
    'include_timezones' => true,  // auto-add VTIMEZONE on serialize
    'filename'          => 'calendar.ics',
    'uid_domain'        => null,  // defaults to APP_URL host
];
```
