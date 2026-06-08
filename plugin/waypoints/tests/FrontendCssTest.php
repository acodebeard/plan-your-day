<?php
declare( strict_types=1 );

namespace Acodebeard\PlanYourDay\Tests;

use PHPUnit\Framework\TestCase;

final class FrontendCssTest extends TestCase {
	public function test_start_option_buttons_have_hover_state(): void {
		$css = $this->fixture( 'assets/css/plan.css' );

		self::assertStringContainsString(
			'.plan-your-day__start-option:hover .plan-your-day__start-option-body',
			$css
		);
		self::assertStringContainsString(
			'.plan-your-day__start-option:active .plan-your-day__start-option-body',
			$css
		);
		self::assertStringContainsString( 'border-color: var(--pyd-accent-border);', $css );
		self::assertStringContainsString( 'box-shadow: var(--pyd-item-hover-shadow);', $css );
	}

	private function fixture( string $path ): string {
		$content = file_get_contents( dirname( __DIR__ ) . '/' . $path );

		self::assertIsString( $content );

		return $content;
	}
}
