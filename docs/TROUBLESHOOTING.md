# Troubleshooting

## Plugin Activation Fails: Missing Autoloader

Source checkouts require Composer:

```sh
cd plugin/plan-your-day
composer install
```

Release zips should already include `vendor/autoload.php`.

## WP-CLI Cannot Connect To Database

For XAMPP installs, make sure Apache and MySQL are running:

```sh
sudo /opt/lampp/lampp start
```

Use XAMPP PHP when testing the DKC install:

```sh
/opt/lampp/bin/php /usr/local/bin/wp \
  --path=/opt/lampp/htdocs/dkc \
  --url=http://localhost/dkc \
  plugin status plan-your-day \
  --allow-root
```

## WordPress Says The Site Is Not Installed

The generic `/opt/lampp/htdocs/wordpress` directory may have WordPress files but
no installed database tables. The current target test site is:

```text
/opt/lampp/htdocs/dkc
```

## Settings Page Does Not Appear In A WP-CLI Smoke Test

`add_options_page()` checks capabilities. If testing menu registration through
WP-CLI, set an administrator user first:

```sh
/opt/lampp/bin/php /usr/local/bin/wp \
  --path=/opt/lampp/htdocs/dkc \
  --url=http://localhost/dkc \
  eval 'wp_set_current_user(1); do_action("admin_menu"); global $submenu; var_export($submenu["options-general.php"] ?? []);' \
  --allow-root
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

## Plugin Settings Are Empty But Legacy DKC Config Exists

Open the plugin settings screen:

```text
http://localhost/dkc/wp-admin/options-general.php?page=plan-your-day
```

If the current WordPress runtime exposes the legacy DKC planner helpers or
constants, the page shows a `Legacy Migration` panel and an admin notice. Use
the import action to copy legacy location values, Google API keys, and legacy
categories into `plan_your_day_settings`.

The importer is conservative:

- It only copies scalar settings when the plugin field is still empty.
- It only imports legacy categories when no custom plugin categories are saved.
- It clears cached Google API responses after import.
