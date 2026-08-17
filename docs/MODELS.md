# Model extension

The `Calendar`, `CalendarEvent`, `EventParticipant`, and `EventAlarm` models can be replaced
under `icalendar.persistence.models`. Relationships and services resolve the configured
classes at call time.

The safest replacement extends the package model:

```php
final class ApplicationCalendarEvent extends CalendarEvent
{
    // Application relationships or methods.
}
```

A replacement that does not extend the supplied model must preserve its `ical_` table,
UUID behavior, casts, configured persistence connection, identifiers, and relationships.
Services assign attributes explicitly and do not depend on mass assignment.

Table names are fixed. The prefix avoids common application table names and keeps published
foreign keys portable.

Calendar ownership is nullable and disabled by default. Enabling it permits storing a
stable `owner_type`/`owner_id` pair without imposing a User model or authorization policy.
Morph aliases must remain stable. A polymorphic foreign key cannot be enforced by the
database, and a non-Eloquent principal may be represented by a stable type token and ID
without resolving `owner()`.
