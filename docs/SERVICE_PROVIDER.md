# Service provider

`ICalendarServiceProvider` is auto-discovered. It always registers the database-free
`ICalendarManager`, facade alias, response macro, validation command, and configuration
publication tag.

Persistence services are container-autowireable and perform no database work during
registration or boot. Package migrations load only when both
`icalendar.persistence.enabled` and `icalendar.persistence.load_migrations` are true.
Otherwise they are available only through the `icalendar-migrations` publication tag.

The provider registers no external calendar transport, authentication, synchronization,
queues, or scheduler integration.
