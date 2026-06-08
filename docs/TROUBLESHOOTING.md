# Troubleshooting

## Plugin Activation Fails: Missing Autoloader

Source checkouts require Composer:

```sh
cd plugin/waypoints
composer install
```

Release zips should already include `vendor/autoload.php`.

## WP-CLI Cannot Connect To Database

Make sure the target WordPress environment and database are actually running.
For example, on a XAMPP install:

```sh
sudo /opt/lampp/lampp start
```

If your WP-CLI setup depends on a non-default PHP binary, call that PHP
explicitly:

```sh
php /path/to/wp \
  --path=/path/to/wordpress \
  --url=https://example.test \
  plugin status waypoints \
```

## WordPress Says The Site Is Not Installed

Your target directory may contain WordPress core files but still have no
installed database tables. Confirm that the `--path` you pass to WP-CLI points
to the actual installed site, not just an unpacked WordPress checkout.

## Settings Page Does Not Appear In A WP-CLI Smoke Test

`add_options_page()` checks capabilities. If testing menu registration through
WP-CLI, set an administrator user first:

```sh
php /path/to/wp \
  --path=/path/to/wordpress \
  --url=https://example.test \
  eval 'wp_set_current_user(1); do_action("admin_menu"); global $submenu; var_export($submenu["options-general.php"] ?? []);' \
```

## Composer Emits Deprecation Notices

The system Composer package can emit deprecation notices on newer PHP versions.
The command can still complete successfully. The important output is that
autoload files were generated.

## Google Results Are Unavailable

Current likely causes:

- Missing Places API key.
- Missing or restricted Geocoding API key, when geocoding is needed.
- Google Cloud APIs not enabled.
- API key restrictions do not allow the server or browser context being used.
- Provider request failure or invalid response.

Use the plugin settings screen, frontend network requests, REST responses, and
WP-CLI checks together when debugging current Google API behavior.
