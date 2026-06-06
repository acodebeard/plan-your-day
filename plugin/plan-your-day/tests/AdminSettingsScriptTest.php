<?php
declare( strict_types=1 );

namespace Acodebeard\PlanYourDay\Tests;

use PHPUnit\Framework\TestCase;

final class AdminSettingsScriptTest extends TestCase {
	public function test_category_sorter_prefers_jquery_ui_sortable_with_vanilla_fallback(): void {
		$script = $this->fixture( 'assets/js/admin-settings.js' );

		self::assertStringContainsString( 'initializeJQueryCategorySorting', $script );
		self::assertStringContainsString( 'initializeVanillaCategorySorting', $script );
		self::assertStringContainsString( 'window.jQuery', $script );
		self::assertStringContainsString( '.sortable(', $script );
		self::assertStringContainsString( 'return initializeVanillaCategorySorting', $script );
	}

	public function test_category_delete_restores_keyboard_focus(): void {
		$script = $this->fixture( 'assets/js/admin-settings.js' );

		self::assertStringContainsString( 'getDeletedCategoryFocusTarget', $script );
		self::assertStringContainsString( 'row.nextElementSibling', $script );
		self::assertStringContainsString( 'row.previousElementSibling', $script );
		self::assertStringContainsString( 'focusTarget.focus({ preventScroll: true })', $script );
	}

	private function fixture( string $path ): string {
		$content = file_get_contents( dirname( __DIR__ ) . '/' . $path );

		self::assertIsString( $content );

		return $content;
	}
}
