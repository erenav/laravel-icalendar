# Application-managed persistence architecture

Status: accepted, superseding the provider-synchronization design (2026-08-15).

## Context

`erenav/icalendar` 0.5.0 remains the authority for RFC parsing, serialization, recurrence,
revision comparison, timezone resolution, and iTIP data. This package supplies an optional
Laravel persistence boundary for calendars managed by the application itself.

External events are not part of this database. Provider authentication, transport,
connections, event mappings, cursors, conflict resolution, and remote lifecycle state are
owned by the host application. There are no existing installations requiring a migration
or compatibility layer.

## Decisions

### Detached overrides are event rows

A detached VEVENT is stored in `ical_calendar_events`, like a standalone event or recurring
master. `component_type` distinguishes `standalone`, `recurring_master`, and
`detached_override`. An override links to its master through nullable
`recurring_master_id`; its RECURRENCE-ID literal, form, TZID, and RANGE are also projected.

This directly models the RFC component and lets an override independently retain sparse
properties, participants, alarms, cancellation, and unknown content. A separate override
table would duplicate event columns and split identical child relationships. Orphan
overrides are permitted so import order does not affect validity.

Generated occurrences are never database rows. Applications reconstruct a core Calendar
and call its bounded `occurrencesBetween()` API.

### Recurrence belongs to the VEVENT

There is no recurrence table. RRULE, RDATE, and EXDATE have no lifecycle separate from their
VEVENT. `rrule` is a convenience projection. Canonical `component_ics` retains ordered and
repeated recurrence properties, parameters, DATE/floating/UTC/TZID values, PERIOD RDATEs,
and unknown RRULE parts.

### Temporal values keep their RFC semantics

DTSTART, explicit DTEND, and RECURRENCE-ID each have literal, form, and TZID columns. Forms
are `date`, `floating`, `utc`, and `zoned`. DURATION has its own RFC literal. Null DTEND and
null DURATION means neither was supplied, leaving implicit duration to the core.

UTC query columns are additional projections, populated only when an instant can be safely
resolved. DATE and floating values are never flattened to UTC. Embedded VTIMEZONE data from
the calendar envelope may resolve custom TZIDs; unresolved zones retain their lexical form
without inventing an instant.

### Identity is local to one stored calendar

The database identity is UID plus either no RECURRENCE-ID or the complete recurrence
instance identity. A SHA-256 projection allows a bounded portable unique index on
`(calendar_id, identity_hash)` while the full UID and component remain stored.

DATE and floating recurrence identities use their typed wall literal. UTC and resolvable
zoned values use their instant. If a resolver-dependent hash changes because an embedded
timezone definition is added or changed, same-UID candidates are checked by typed semantic
and lexical identity before a new row is created.

There is no global matching, title/time matching, external ID, or provider scope.

### RFC content is canonical; projections are searchable

The complete VCALENDAR is retained as an envelope and ordering template. Each VEVENT,
participant property, and VALARM is also retained as canonical ICS. These payloads preserve
unknown properties and parameters, duplicate properties, embedded timezones, custom
components, organizer/attendee detail, alarms, and recurrence ordering.

Normalized columns support identity and common application queries; they are refreshed by
package services. Directly editing a convenience column does not edit the canonical
component and is unsupported.

Calendar export replaces VEVENT placeholders in the envelope with current event rows and
appends newly created events. Import is an upsert, not a mirror operation: omitting an event
from a later ICS document does not delete an existing application-managed event.

### Revisions are only an ICS import concern

Duplicate components and existing imported rows use core 0.5.0
`EventRevisionComparator`: SEQUENCE, UTC DTSTAMP, UTC LAST-MODIFIED, then canonical content.
Malformed comparison metadata is invalid, an older component is skipped, an identical
component is unchanged, and a newer component updates the row. This is deterministic input
selection, not remote conflict resolution.

### Calendars may have an optional application owner

`owner_type` and `owner_id` are nullable and disabled by default. Enabling ownership permits
a morph target or stable non-Eloquent principal identifier without assuming a User model,
tenancy, or authorization system. Morph aliases must remain stable. The database cannot
foreign-key a polymorphic target, so the pair has a non-unique index.

### Models are replaceable; table names are fixed

All four supplied models have configuration entries. Relationships and services resolve
configured classes. Replacement models should extend the package models or reproduce their
table, UUID, cast, connection, and relationship invariants. Fixed `ical_` table names avoid
common application collisions and keep published foreign keys coherent.

### Persistence and migrations are opt-in

`icalendar.persistence.enabled` and `persistence.load_migrations` both default to false.
Migrations are publishable under `icalendar-migrations`; automatic loading requires both
options to be true. ICS parsing, serialization, iTIP attachments, responses, validation,
and application-model projection never require these tables.

Calendar/core imports and event-child reconciliation use database transactions. The
migration and supplied models honor `icalendar.persistence.connection` when configured.
An import containing any invalid VEVENT reports every detected validation failure and
performs no writes. `createFromCore()` throws for that invalid input so its newly created
calendar is rolled back as well.

### Deletion is application-controlled

No remote or local synchronization tombstone is stored. Deleting a Calendar cascades to
its events, participants, and alarms. Deleting an event cascades to participants and alarms;
deleting a recurring master nulls detached override links. Applications that need archival
or soft deletion can provide replacement models and their own schema policy.

## Deliberate limitations

The package does not authenticate with providers, fetch URLs or provider calendars,
interpret provider payloads, map external IDs, synchronize remote changes, store
credentials, advance cursors, resolve application conflicts, schedule jobs, deliver alarm
notifications, persist generated occurrences, or define authorization.

Unknown RRULE extensions are preserved but expansion remains subject to the core's
fail-closed behavior.

Round trips preserve the parsed component tree and RFC value distinctions, not original
wire bytes. The core serializer normalizes details such as line folding, case, escaping,
and parameter quoting.
