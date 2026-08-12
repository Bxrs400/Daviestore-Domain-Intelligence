# Passive Laravel detection

Technology detection runs additively after HTTP validation for verified domains. It only inspects the public response already fetched by the crawler: headers, `Set-Cookie`, HTML/source, public asset markers, and naturally returned framework identifiers.

It never requests `.env`, debug, admin, phpunit, configuration, or other sensitive paths, and it does not perform exploitation or vulnerability testing.

Stored fields:

- `technology.laravel_detected`
- `technology.laravel_confidence`
- `technology.laravel_signals`
- `technology.detection_method`
- `technology.checked_at`
- `laravel_confidence`, `laravel_confidence_label`, `technology_checked_at`

Jobs may request filtering with:

```json
{"options":{"technology":["laravel"],"minimum_technology_confidence":70}}
```

Domains API and CSV export support `technology=laravel&minimum_technology_confidence=70`.
