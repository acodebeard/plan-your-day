# Issue 33 Google API Client Notes

The plugin exposes the Google API layer through:

```php
$client = \Acodebeard\PlanYourDay\Plugin::instance()->google_api_client();
```

REST handlers should depend on `GoogleApiClientInterface` where practical and call:

- `text_search( $query, $origin_latitude, $origin_longitude )`
- `place_details( $place_id )`
- `geocode( $address )`

Each method returns a `GoogleApiResult`. Check `is_success()` before reading `data()`. On failure, use `error_code()`, `message()`, `status_code()`, or `to_array()` rather than inspecting raw Google responses. The messages are intentionally safe for user-facing API responses and do not include provider payloads or API keys.

Settings are stored in the `plan_your_day_settings` option and registered through the Settings API. The browser-facing Maps Embed key is separate from server-side Places and Geocoding keys. If the Geocoding key is blank, the Settings service falls back to the Places key for server-side geocoding.

Caching is added as a wrapper/decorator around `GoogleApiClientInterface` in issue #34. Cache keys are derived from request inputs and an HMAC hash of the server-side key, never from the raw API key.

REST endpoints in issue #30 should call this client after request schema validation, token checks, waypoint limits, and any rate-limit checks owned by issues #31 and #32.
