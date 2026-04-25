# Google API Key Restrictions

This repository enforces **key separation**, but it cannot prove that your
Google Cloud project has the right restrictions configured. Use this guide
before putting the plugin on a public site.

## What The Code Already Does

- `google_maps_embed_api_key` is browser-facing and can appear in frontend
  iframe URLs.
- `google_places_api_key` is used server-side for Places API (New) requests.
- `google_geocoding_api_key` is used server-side for Geocoding API requests, or
  the Places key is reused when the dedicated geocoding key is empty.
- Cache keys hash the API key material instead of storing raw key strings.

That means the plugin keeps Places and Geocoding keys out of the browser, but
the project still depends on **Google Cloud Console restrictions** to prevent
abuse.

## Recommended Key Layout

Use at least two keys:

1. One browser-facing key for Maps Embed.
2. One server-side key for Places API (New).

Add a third key when you want Geocoding separated from Places for quota or
incident isolation.

## Browser Key: Maps Embed API

For the Maps Embed key:

- Restrict the key by **HTTP referrer**.
- Allow only the production hostnames that should render the planner.
- Enable only the **Maps Embed API** on that key.

Example referrers:

- `https://example.com/*`
- `https://www.example.com/*`
- `https://staging.example.com/*`

Avoid broad wildcards such as `https://*.example.com/*` unless you actually
control every matching host.

## Server Keys: Places And Geocoding

For Places and Geocoding keys:

- Treat them as **server credentials**, not frontend credentials.
- Do not paste them into theme JavaScript, block attributes, or page builders.
- Enable only the Google APIs that each key needs.
- Restrict them by the strongest server-side restriction your environment can
  support.

Common restriction options:

- Source IP allowlists for fixed-host deployments.
- Separate projects or separate keys per environment.
- Distinct keys for staging and production.

If your host rotates egress IPs and you cannot maintain an allowlist, reduce
blast radius by splitting keys per environment and by enabling only the exact
APIs required for each key.

## Minimum API Enablement

Recommended API enablement by key:

- Maps Embed key: `Maps Embed API`
- Places key: `Places API (New)`
- Geocoding key: `Geocoding API`

Do not leave unrelated APIs enabled "just in case".

## Validation Checklist

Before launch, verify:

- The frontend only exposes the Maps Embed key.
- The plugin settings screen stores Places and Geocoding keys, but runtime page
  output does not print them.
- Browser key requests fail from unauthorized referrers.
- Server-side requests fail when replayed with the wrong key or from the wrong
  environment.
- Quota and billing alerts are configured in Google Cloud.

## Operational Notes

- Rotate the browser key and server keys independently.
- If one server-side key is suspected to be stale or compromised, use the
  plugin cache tools to clear only the affected API scope or place data after
  rotation.
- Re-check restrictions whenever you clone a site to staging or move hosting
  providers.
