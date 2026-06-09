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

	public function test_api_key_reveal_buttons_briefly_switch_inputs_to_plain_text(): void {
		$script = $this->fixture( 'assets/js/admin-settings.js' );

		self::assertStringContainsString( 'API_KEY_REVEAL_DURATION_MS', $script );
		self::assertStringContainsString( 'initializeApiKeyRevealButtons', $script );
		self::assertStringContainsString( '[data-plan-api-key-reveal]', $script );
		self::assertStringContainsString( '[data-plan-api-key-input]', $script );
		self::assertStringContainsString( "input.type = 'text'", $script );
		self::assertStringContainsString( "input.type = 'password'", $script );
		self::assertStringContainsString( "button.setAttribute('aria-pressed', 'true')", $script );
		self::assertStringContainsString( 'window.setTimeout(hideKey, API_KEY_REVEAL_DURATION_MS)', $script );
		self::assertStringContainsString( "button.addEventListener('pointerdown'", $script );
		self::assertStringContainsString( "input.addEventListener('blur', hideKey)", $script );
	}

	private function fixture( string $path ): string {
		$content = file_get_contents( dirname( __DIR__ ) . '/' . $path );

		self::assertIsString( $content );

		return $content;
	}
}
