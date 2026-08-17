# Changelog

All notable changes to `erenav/laravel-icalendar` are documented here. The format is based
on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and this project adheres to
[Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [0.5.2] - 2026-08-17

### Fixed
- Integer model identifiers now remain integers across event replacement, mapping,
  imports, recurrence linking, child foreign keys, and import results.
- Independent-model coverage now uses native auto-increment keys and matching foreign keys
  and runs against SQLite, MySQL, and PostgreSQL in CI.

## [0.5.1] - 2026-08-17

### Added
- Independent replacement models may use scalar or stringable non-UUID keys when paired
  with a compatible application migration.

## [0.5.0] - 2026-08-17

### Added
- Configurable persistence table names through `icalendar.persistence.tables`.

### Breaking
- Laravel 12 is now required. Laravel 11 has reached end of security support and is no
  longer included in the supported dependency or CI matrix.

## [0.4.0] - 2026-08-17

### Added
- Optional application-managed persistence for calendars, VEVENT components, participants,
  and alarms. Migrations are publish-only by default and
  can be explicitly auto-loaded for persistence-enabled applications.
- Detached recurrence overrides as self-linked event rows, with no rows for generated
  occurrences; canonical ICS preservation alongside normalized identity, temporal,
  revision, and participation fields.
- Bidirectional core conversion, transactional ICS/core imports, structured import
  results and previews, stored event/calendar export, core revision comparison, and stale
  revision rejection.
- Replaceable model registry, nullable optional ownership, persistence/configuration/model/
  schema documentation, and a complete persistence ADR.

### Changed
- Core dependency is now `erenav/icalendar ^0.5.0`; Illuminate Database is an explicit
  runtime dependency.
- Package positioning expands from thin ICS glue to optional application-managed Laravel
  persistence while retaining database-free response, attachment, parser,
  serializer, validation, and application-model projection APIs.
- Persistence now validates unambiguous UID/RECURRENCE-ID and revision metadata, matches
  resolvable recurrence instances by instant, and keeps series links coherent after local
  identity changes. Migrations honor the configured persistence
  connection for both install and rollback.
- Resolver-dependent custom-timezone identity projections now fall back to typed
  lexical/semantic matching and refresh safely without duplicate rows.
- The persistence scope is limited to application-managed calendars. Experimental calendar
  connections, external mappings, credentials, provider adapters, synchronization cursors,
  conflict policies, remote tombstones, and push/pull orchestration were removed before
  release because external provider events belong to the host application.

### Breaking
- Persistence is a clean four-table initial schema with fixed `ical_` table names and no
  legacy migration or compatibility layer. The discarded synchronization APIs have no
  compatibility shims.

## [0.3.0] - 2026-06-20

### Changed
- Widened the `erenav/icalendar` requirement to `>=0.2 <2.0` so the package can use newer
  pre-1.0 core releases (recurrence overrides, iTIP, …) instead of being pinned to `0.2.x`.

### Internal
- PHPStan (Larastan) at `level: max` and Pint added and enforced in CI.

## [0.2.0] - 2026-06-20

### Added
- `CalendarAttachment` now advertises the calendar's iTIP METHOD in the MIME type
  (`text/calendar; method=REQUEST`), so mail clients recognise the attachment as an
  invitation.

## [0.1.0] - 2026-06-20

### Added
- Initial Laravel integration for [`erenav/icalendar`](https://github.com/erenav/icalendar).
- Auto-discovered service provider, publishable `config/icalendar.php`, and the
  `ICalendar` facade over an `ICalendarManager` (config-aware `calendar()`, `event()`,
  `parse()`, `serialize()`, `fromModels()`, `response()`).
- Eloquent mapping: `ProvidesCalendarEvent` contract + `InteractsWithCalendar` trait
  (deterministic UIDs, `toIcs()`).
- HTTP: `CalendarResponse` (`Responsable`) and a `response()->ics()` macro for serving
  `text/calendar` feeds.
- `CalendarAttachment::for()` to attach a calendar to Mailables / notification mail messages.
- `icalendar:validate` Artisan command.

[Unreleased]: https://github.com/erenav/laravel-icalendar/compare/v0.5.2...HEAD
[0.5.2]: https://github.com/erenav/laravel-icalendar/compare/v0.5.1...v0.5.2
[0.5.1]: https://github.com/erenav/laravel-icalendar/compare/v0.5.0...v0.5.1
[0.5.0]: https://github.com/erenav/laravel-icalendar/compare/v0.4.0...v0.5.0
[0.4.0]: https://github.com/erenav/laravel-icalendar/compare/0.3.0...0.4.0
[0.3.0]: https://github.com/erenav/laravel-icalendar/compare/0.2.0...0.3.0
[0.2.0]: https://github.com/erenav/laravel-icalendar/compare/0.1.0...0.2.0
[0.1.0]: https://github.com/erenav/laravel-icalendar/releases/tag/0.1.0
