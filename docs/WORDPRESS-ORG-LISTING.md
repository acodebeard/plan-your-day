# WordPress.org Listing

WordPress.org listing copy and artwork are maintained separately from plugin
runtime assets.

## Readme Files

- `plugin/waypoints/readme.txt` is the canonical WordPress.org plugin readme.
- `plugin/waypoints/readme.html` is a formatted source preview of the same
  public-facing content.

Keep both files aligned when changing public readme copy. The formatted preview
is excluded from the installable GitHub release zip; `readme.txt` remains in
the package.

## Listing Artwork

The repository root `assets/` directory mirrors the top-level `assets/`
directory used by the WordPress.org plugin SVN repository. Current files:

- `assets/icon-128x128.png`
- `assets/icon-256x256.png`
- `assets/screenshot-1.png`

The screenshot caption lives in `plugin/waypoints/readme.txt` under
`== Screenshots ==`. Keep the matching caption in `plugin/waypoints/readme.html`
in sync.

Do not move these files into `plugin/waypoints/assets/`; that directory is for
runtime CSS, JavaScript, fonts, and other plugin-loaded assets.

## Readme-Only Updates

Readme-only listing updates may change `plugin/waypoints/readme.txt`,
`plugin/waypoints/readme.html`, and root `assets/` without bumping the plugin
version or rebuilding the GitHub release zip, as long as no runtime plugin files
change.

When publishing to WordPress.org SVN, copy the updated `readme.txt` into both
`trunk/readme.txt` and the current stable tag path, such as
`tags/1.0.2/readme.txt`. WordPress.org reads listing content from the stable tag
when `Stable tag` points at a versioned tag.

Before publishing a listing-only update, run:

```sh
php plugin/waypoints/tools/check-wp-submission-readiness.php --plugin-dir=plugin/waypoints
tidy -errors -quiet plugin/waypoints/readme.html
identify -format '%f %wx%h %m %b\n' assets/icon-128x128.png assets/icon-256x256.png assets/screenshot-1.png
```
