# Issue 34 Google API Cache Notes

Google API caching is implemented as `CachedGoogleApiClient`, a decorator around `GoogleApiClientInterface`.

The plugin root still exposes the same client entry point:

```php
$client = \Acodebeard\PlanYourDay\Plugin::instance()->google_api_client();
```

The decorator caches successful responses from:

- `text_search()`
- `place_details()`
- `geocode()`

Failed responses are not cached. This keeps setup fixes, API key changes, provider recovery, and validation changes from being hidden behind a stale failure.

Cache storage uses WordPress transients. Sites with a persistent object cache will use that object cache through WordPress' transient API; other sites use the normal options-backed transient behavior.

Cache keys are generated from the request scope, request inputs, and an HMAC hash of the relevant server-side API key. Raw API keys are not stored in transient names or the tracked cache-key option.

Configurable TTL settings live in `plan_your_day_settings`:

- `google_text_search_cache_ttl`, default `21600`
- `google_place_details_cache_ttl`, default `86400`
- `google_geocoding_cache_ttl`, default `86400`

TTL values are seconds, clamped from `0` through `WEEK_IN_SECONDS`. A value of `0` disables caching for that response type.

The admin settings screen includes a nonce-protected clear-cache tool. It deletes every transient tracked by the Google API cache index and then removes the index option.
