# Security Model

This document covers the security behavior implemented so far for the
WordPress plugin.

## Current Key Handling

Google keys are separated by use:

- Maps Embed API key is browser-facing.
- Places API key is server-side.
- Geocoding API key is server-side and optional.

Server-side keys are read by backend services and must not be emitted in
frontend runtime config.

## Current API Client Protections

The Google API client:

- Uses explicit Places API field masks.
- Uses configurable request timeouts.
- Returns safe user-facing messages instead of raw provider errors.
- Avoids exposing API keys in transient cache keys.
- Caches successful provider responses through WordPress transients.

## Current Admin Protections

The settings page:

- Requires `manage_options`.
- Uses the WordPress Settings API.
- Uses WordPress nonces for the cache clear tool.
- Escapes admin output.
- Sanitizes every registered setting before saving.

## Current Public Endpoint Protections

`RequestOriginValidator` implements a same-site heuristic for public REST
request handling:

- Fetch Metadata headers are preferred when browsers send them.
- Cross-site subresource requests are rejected.
- User-activated top-level document navigations can still be allowed.
- `Origin` and `Referer` fallbacks compare against the expected host and port.

The public planner endpoints also use:

- WordPress REST routes for browse and route preview requests.
- POST-only route methods.
- Request schemas and request-field sanitization.
- Guest-safe visitor token cookie.
- HMAC endpoint token generation and validation.
- File-backed rate limiter.
- Trusted-proxy-aware client IP resolution.
- Structured REST errors for bad requests and invalid tokens.

The standalone runtime still contains session-backed endpoint protection and
rate limiting. Those behaviors remain migration reference, not the final plugin
implementation.
