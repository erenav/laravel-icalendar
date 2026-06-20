# Changelog

All notable changes to `vanere/laravel-icalendar` are documented here. The format is based
on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and this project adheres to
[Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

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
