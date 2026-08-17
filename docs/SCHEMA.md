# Initial persistence schema

All primary and foreign keys are UUID strings. Every table has `created_at` and
`updated_at`. The names below are defaults and can be changed with
`icalendar.persistence.tables` before running the initial migration.

## `ical_calendars`

`id`, `name`, nullable `description`, `timezone`, and `color`, `source_type` (`internal` or
`imported`), `enabled`, nullable `owner_type`/`owner_id`, and complete VCALENDAR
`component_ics`. Source type, enabled state, and owner pair are indexed.

## `ical_calendar_events`

`id`, `calendar_id`, nullable self-referencing `recurring_master_id`, `identity_hash`,
`uid_hash`, full `uid`, and `component_type`; RECURRENCE-ID literal/type/TZID/RANGE;
summary, description, location, URL; DTSTART and DTEND literal/type/TZID/nullable UTC query
instant; DURATION and source timezone; status, transparency, classification, priority,
sequence, color; normalized CREATED, DTSTAMP, and LAST-MODIFIED; canonical RRULE
convenience text; cancellation; and complete VEVENT `component_ics`.

Calendar deletion cascades; deleting a master nulls override links. The unique
`(calendar_id, identity_hash)` constraint enforces UID plus recurrence-instance identity
within a calendar. UID, component type, UTC instants, status, cancellation, and
`(recurring_master_id, recurrence_id_value)` are indexed.

DATE/floating instance identities use wall literals. UTC and safely resolved TZID values
use their instant. A hash miss also checks same-UID candidates using typed semantic and
lexical identity so changing an embedded timezone definition updates rather than duplicates
an override.

## `ical_event_participants`

`id`, `event_id`, ordered `position`, organizer/attendee `type`, calendar address, common
name, ROLE, PARTSTAT, CUTYPE, nullable RSVP, MEMBER, DELEGATED-TO/FROM, SENT-BY, DIR,
LANGUAGE, unknown parameters, and preserved property ICS. Event deletion cascades and
`(event_id, position)` is unique.

## `ical_event_alarms`

`id`, `event_id`, ordered `position`, action, trigger value/type, description, summary,
repeat count/duration, and complete VALARM `component_ics`. Event deletion cascades and
`(event_id, position)` is unique.

## Portability

The migration uses Laravel UUID/string/text/JSON/boolean/integer/date-time primitives,
ordinary foreign keys, and explicit indexes supported by SQLite, MySQL, and PostgreSQL.
It uses no database enums, generated columns, partial/expression indexes, JSON-path indexes,
or engine-specific defaults. A configured persistence connection controls both install and
rollback.
