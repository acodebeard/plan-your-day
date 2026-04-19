# Security Model

This document covers the security behavior implemented so far and the pieces
still planned for the WordPress plugin.

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

## Current Request-Origin Helper

`RequestOriginValidator` implements a same-site heuristic for future public
request handling:

- Fetch Metadata headers are preferred when browsers send them.
- Cross-site subresource requests are rejected.
- User-activated top-level document navigations can still be allowed.
- `Origin` and `Referer` fallbacks compare against the expected host and port.

This helper is not yet wired to public REST routes because those routes do not
exist yet.

## Planned Public Endpoint Protections

Still planned:

- WordPress REST routes for browse and route preview requests.
- POST-only route methods.
- Request schemas and request-field sanitization.
- Guest-safe visitor token cookie.
- HMAC endpoint token generation and validation.
- Object-cache-aware or file-backed rate limiter.
- Trusted-proxy-aware client IP resolution.
- Structured REST errors for bad requests and invalid tokens.

The standalone runtime still contains session-backed endpoint protection and
rate limiting. Those behaviors are migration reference, not the final plugin
implementation.
