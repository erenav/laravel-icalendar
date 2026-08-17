# Configuration

`product_id`, `strict`, `include_timezones`, `filename`, and `uid_domain` configure the
database-free manager and helpers.

Persistence settings are under `icalendar.persistence`:

- `enabled`: enables persistence services; default `false`.
- `load_migrations`: loads package migrations only when persistence is enabled; default
  `false`. Publishing migrations is recommended.
- `connection`: optional Laravel database connection name. Models and both migration
  directions use it.
- `models.calendar`, `models.event`, `models.participant`, and `models.alarm`: replacement
  Eloquent model classes.
- `owner.enabled`: allows assigning optional polymorphic owner identifiers.

Publish configuration with:

```bash
php artisan vendor:publish --tag=icalendar-config
```

Configuration publication does not create tables. Migrations have the separate
`icalendar-migrations` tag.
