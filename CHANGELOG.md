# Changelog

All notable changes to `vanere/laravel-icalendar` are documented here. The format is based
on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and this project adheres to
[Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [0.3.0] - 2026-06-20

### Changed
- Widened the `vanere/icalendar` requirement to `>=0.2 <2.0` so the package can use newer
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
- Initial Laravel integration for [`vanere/icalendar`](https://github.com/vanere/icalendar).
- Auto-discovered service provider, publishable `config/icalendar.php`, and the
  `ICalendar` facade over an `ICalendarManager` (config-aware `calendar()`, `event()`,
  `parse()`, `serialize()`, `fromModels()`, `response()`).
- Eloquent mapping: `ProvidesCalendarEvent` contract + `InteractsWithCalendar` trait
  (deterministic UIDs, `toIcs()`).
- HTTP: `CalendarResponse` (`Responsable`) and a `response()->ics()` macro for serving
  `text/calendar` feeds.
- `CalendarAttachment::for()` to attach a calendar to Mailables / notification mail messages.
- `icalendar:validate` Artisan command.

[Unreleased]: https://github.com/vanere/laravel-icalendar/compare/0.3.0...HEAD
[0.3.0]: https://github.com/vanere/laravel-icalendar/compare/0.2.0...0.3.0
[0.2.0]: https://github.com/vanere/laravel-icalendar/compare/0.1.0...0.2.0
[0.1.0]: https://github.com/vanere/laravel-icalendar/releases/tag/0.1.0
