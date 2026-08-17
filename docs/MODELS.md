# Model extension

The `Calendar`, `CalendarEvent`, `EventParticipant`, and `EventAlarm` models can be replaced
under `icalendar.persistence.models`. Relationships and services resolve the configured
classes at call time.

The simplest replacement extends the package model:

```php
final class ApplicationCalendarEvent extends CalendarEvent
{
    // Application relationships or methods.
}
```

A replacement may instead extend Eloquent's base `Model` directly. Package services assign
attributes explicitly, accept scalar or stringable primary keys, and do not require
`HasUuids`, package-model inheritance, mass assignment, or relationships. Independent
models must provide compatible columns and casts, and their primary and foreign-key column
types must agree. Publish and customize the migration when using bigint, ULID, or another
key strategy.

The supplied models and migration use UUID keys and configurable `ical_` table names by
default. UUID remains the supported turnkey schema; custom key strategies are an advanced
model-and-migration customization rather than a runtime configuration switch.

Calendar ownership is nullable and disabled by default. Enabling it permits storing a
stable `owner_type`/`owner_id` pair without imposing a User model or authorization policy.
Morph aliases must remain stable. A polymorphic foreign key cannot be enforced by the
database, and a non-Eloquent principal may be represented by a stable type token and ID
without resolving `owner()`.
