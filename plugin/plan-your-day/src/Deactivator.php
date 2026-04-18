<?php
declare( strict_types=1 );

namespace PlanYourDay;

defined( 'ABSPATH' ) || exit;

final class Deactivator {
	public static function deactivate(): void {
		// Intentionally non-destructive.
		// Cleanup of plugin state (options, scheduled events) happens in uninstall.php.
	}
}
