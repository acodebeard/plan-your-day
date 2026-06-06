# PHPStan

This plugin uses PHPStan as a development-only Composer dependency.

Install dependencies:

```bash
composer install
```

Run PHPStan from the plugin directory:

```bash
composer run phpstan
```

The config scans `plan-your-day.php`, `uninstall.php`, and `src/`. WordPress
symbols are provided by `php-stubs/wordpress-stubs`, and plugin constants are
defined in `tools/phpstan/bootstrap.php` so the scan does not require a running
WordPress install.
