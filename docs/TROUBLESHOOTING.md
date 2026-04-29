# Troubleshooting

## Plugin Activation Fails: Missing Autoloader

Source checkouts require Composer:

```sh
cd plugin/plan-your-day
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
  plugin status plan-your-day \
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

The frontend planner is not wired yet, so current Google behavior is mostly
validated through WP-CLI or future REST/renderer work.

## Plugin Settings Are Empty But Legacy Planner Settings Exist

Open the plugin settings screen:

```text
https://example.test/wp-admin/options-general.php?page=plan-your-day
```

If you are upgrading from an earlier standalone planner, expose its settings to
the plugin before activation. The plugin supports two generic inputs:

- Define `PLAN_YOUR_DAY_LEGACY_CONFIG_FILE` to a PHP file that returns a legacy
  settings array using plugin setting keys.
- Or provide the same array through the `plan_your_day_legacy_settings` filter.

Activation imports that legacy data into `plan_your_day_settings` only when the
plugin settings are still effectively empty.

The importer is conservative:

- It only copies scalar settings when the plugin field is still empty.
- It only imports legacy categories when no custom plugin categories are saved.
- It clears cached Google API responses after import.
