# Recipes

## Serve or attach ICS without persistence

```php
$calendar = ICalendar::calendar()->add($event)->get();

return ICalendar::response($calendar, 'events.ics');
// return response()->ics($calendar, 'events.ics');
// CalendarAttachment::for($calendar, 'invite.ics');
```

For an iTIP message, set the calendar method before attaching it:

```php
$request = ICalendar::calendar()->method('REQUEST')->add($event)->get();
$attachment = CalendarAttachment::for($request, 'invite.ics');
```

## Import an uploaded calendar

```php
$calendar = app(CalendarStore::class)->create('Imports');
$result = app(CalendarImporter::class)->importIcs(
    $calendar,
    $request->file('calendar')->get(),
);

if ($result->invalid !== []) {
    // Present invalid components to the application. Nothing was persisted.
}
```

Preview parsing and duplicate revision selection without writes:

```php
$preview = app(CalendarImporter::class)->previewIcs($ics);
$preview->selectedEvents;
$preview->discardedRevisions;
$preview->discardedRevisionIds;
$preview->invalid;
```

## Create an internal event

```php
$calendar = app(CalendarStore::class)->create('Internal calendar');

$record = app(CalendarStore::class)->putEvent(
    $calendar,
    Event::build()
        ->uid('meeting@app.test')
        ->starts(DateTimeValue::zoned($wallTime, 'America/New_York'))
        ->lasting(Duration::hours(1))
        ->summary('Planning')
        ->get(),
);
```

For recurring data, store the RRULE master and only explicit detached VEVENT overrides:

```php
$records = app(CalendarStore::class)->upsertRecurringSeries(
    $calendar,
    $master,
    $overrides,
);
```

Generated occurrences remain transient. Expand through a bounded core window:

```php
$core = app(StoredCalendarExporter::class)->calendar($calendar);
$occurrences = $core->occurrencesBetween($from, $to);
```

## Export stored data

```php
$exporter = app(StoredCalendarExporter::class);

$calendarIcs = $exporter->calendarIcs($calendarRecord);
$singleEventCalendarIcs = $exporter->eventIcs($eventRecord);
$eventComponentIcs = $exporter->eventComponentIcs($eventRecord);
```

## Use optional ownership

```php
// config: icalendar.persistence.owner.enabled = true
app(CalendarStore::class)->assignOwner($calendar, 'account', (string) $accountId);
```

The type/ID may represent an Eloquent morph target or another stable application
principal. Authorization remains application-owned.
