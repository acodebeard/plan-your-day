<?php
declare( strict_types=1 );

require dirname( __DIR__ ) . '/bootstrap.php';

if ( ! defined( 'PLAN_YOUR_DAY_PLUGIN_DIR' ) ) {
	define( 'PLAN_YOUR_DAY_PLUGIN_DIR', dirname( __DIR__, 2 ) . '/' );
}

if ( ! defined( 'PLAN_YOUR_DAY_PLUGIN_URL' ) ) {
	define( 'PLAN_YOUR_DAY_PLUGIN_URL', trailingslashit( plan_your_day_browser_base_url() ) . 'plugin/plan-your-day/' );
}

$GLOBALS['plan_your_day_browser_registered_styles'] = [];
$GLOBALS['plan_your_day_browser_registered_scripts'] = [];
$GLOBALS['plan_your_day_browser_enqueued_styles']   = [];
$GLOBALS['plan_your_day_browser_enqueued_scripts']  = [];
$GLOBALS['plan_your_day_browser_unique_id']         = 0;

plan_your_day_browser_load_state();
register_shutdown_function( 'plan_your_day_browser_persist_state' );

if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( string $text ): string {
		return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_html__' ) ) {
	function esc_html__( string $text, ?string $domain = null ): string {
		unset( $domain );

		return esc_html( $text );
	}
}

if ( ! function_exists( 'esc_html_e' ) ) {
	function esc_html_e( string $text, ?string $domain = null ): void {
		echo esc_html__( $text, $domain );
	}
}

if ( ! function_exists( 'esc_attr' ) ) {
	function esc_attr( string $text ): string {
		return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_url' ) ) {
	function esc_url( string $url ): string {
		return htmlspecialchars( trim( $url ), ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'checked' ) ) {
	function checked( mixed $checked, mixed $current = true, bool $display = true ): string {
		$output = $checked === $current ? 'checked="checked"' : '';

		if ( $display ) {
			echo $output;
		}

		return $output;
	}
}

if ( ! function_exists( 'disabled' ) ) {
	function disabled( mixed $disabled, bool $current = true, bool $display = true ): string {
		$output = (bool) $disabled === $current ? 'disabled="disabled"' : '';

		if ( $display ) {
			echo $output;
		}

		return $output;
	}
}

if ( ! function_exists( 'shortcode_atts' ) ) {
	function shortcode_atts( array $pairs, array $atts, string $shortcode = '' ): array {
		unset( $shortcode );

		$normalized = [];

		foreach ( $pairs as $key => $default ) {
			$normalized[ $key ] = array_key_exists( $key, $atts ) ? $atts[ $key ] : $default;
		}

		return $normalized;
	}
}

if ( ! function_exists( 'wp_unique_id' ) ) {
	function wp_unique_id( string $prefix = '' ): string {
		$GLOBALS['plan_your_day_browser_unique_id'] = (int) ( $GLOBALS['plan_your_day_browser_unique_id'] ?? 0 ) + 1;

		return $prefix . $GLOBALS['plan_your_day_browser_unique_id'];
	}
}

if ( ! function_exists( 'number_format_i18n' ) ) {
	function number_format_i18n( float $number, int $decimals = 0 ): string {
		return number_format( $number, $decimals, '.', ',' );
	}
}

if ( ! function_exists( 'current_user_can' ) ) {
	function current_user_can( string $capability ): bool {
		unset( $capability );

		return false;
	}
}

if ( ! function_exists( 'admin_url' ) ) {
	function admin_url( string $path = '' ): string {
		return plan_your_day_browser_join_url( plan_your_day_browser_base_url() . '/wp-admin', $path );
	}
}

if ( ! function_exists( 'home_url' ) ) {
	function home_url( string $path = '' ): string {
		return plan_your_day_browser_join_url( plan_your_day_browser_base_url(), $path );
	}
}

if ( ! function_exists( 'rest_url' ) ) {
	function rest_url( string $path = '' ): string {
		return plan_your_day_browser_join_url( plan_your_day_browser_base_url() . '/wp-json', $path );
	}
}

if ( ! function_exists( 'get_queried_object_id' ) ) {
	function get_queried_object_id(): int {
		return 0;
	}
}

if ( ! function_exists( 'get_permalink' ) ) {
	function get_permalink( int $post_id ): string|false {
		unset( $post_id );

		return false;
	}
}

if ( ! function_exists( 'wp_register_style' ) ) {
	function wp_register_style( string $handle, string $src = '', array $deps = [], string|bool|null $ver = false, string $media = 'all' ): void {
		$GLOBALS['plan_your_day_browser_registered_styles'][ $handle ] = [
			'src'   => $src,
			'deps'  => $deps,
			'ver'   => $ver,
			'media' => $media,
		];
	}
}

if ( ! function_exists( 'wp_register_script' ) ) {
	function wp_register_script( string $handle, string $src = '', array $deps = [], string|bool|null $ver = false, array|bool $args = [] ): void {
		$GLOBALS['plan_your_day_browser_registered_scripts'][ $handle ] = [
			'src'  => $src,
			'deps' => $deps,
			'ver'  => $ver,
			'args' => $args,
		];
	}
}

if ( ! function_exists( 'wp_enqueue_style' ) ) {
	function wp_enqueue_style( string $handle, string $src = '', array $deps = [], string|bool|null $ver = false, string $media = 'all' ): void {
		$registered = $GLOBALS['plan_your_day_browser_registered_styles'][ $handle ] ?? [];

		$GLOBALS['plan_your_day_browser_enqueued_styles'][ $handle ] = [
			'src'   => '' !== $src ? $src : (string) ( $registered['src'] ?? '' ),
			'deps'  => [] !== $deps ? $deps : (array) ( $registered['deps'] ?? [] ),
			'ver'   => false !== $ver ? $ver : ( $registered['ver'] ?? false ),
			'media' => 'all' !== $media ? $media : (string) ( $registered['media'] ?? 'all' ),
		];
	}
}

if ( ! function_exists( 'wp_enqueue_script' ) ) {
	function wp_enqueue_script( string $handle, string $src = '', array $deps = [], string|bool|null $ver = false, array|bool $args = [] ): void {
		$registered = $GLOBALS['plan_your_day_browser_registered_scripts'][ $handle ] ?? [];

		$GLOBALS['plan_your_day_browser_enqueued_scripts'][ $handle ] = [
			'src'  => '' !== $src ? $src : (string) ( $registered['src'] ?? '' ),
			'deps' => [] !== $deps ? $deps : (array) ( $registered['deps'] ?? [] ),
			'ver'  => false !== $ver ? $ver : ( $registered['ver'] ?? false ),
			'args' => [] !== $args ? $args : (array) ( $registered['args'] ?? [] ),
		];
	}
}

function plan_your_day_browser_base_url(): string {
	$base_url = getenv( 'PLAN_YOUR_DAY_BROWSER_BASE_URL' );

	if ( is_string( $base_url ) && '' !== trim( $base_url ) ) {
		return rtrim( trim( $base_url ), '/' );
	}

	$scheme = ! empty( $_SERVER['HTTPS'] ) && 'off' !== (string) $_SERVER['HTTPS'] ? 'https' : 'http';
	$host   = trim( (string) ( $_SERVER['HTTP_HOST'] ?? '127.0.0.1:9080' ) );

	return $scheme . '://' . $host;
}

function plan_your_day_browser_state_file(): string {
	$state_file = getenv( 'PLAN_YOUR_DAY_BROWSER_STATE_FILE' );

	if ( is_string( $state_file ) && '' !== trim( $state_file ) ) {
		return trim( $state_file );
	}

	return dirname( __DIR__, 4 ) . '/tmp/plan-your-day-browser-state.json';
}

function plan_your_day_browser_join_url( string $base, string $path = '' ): string {
	$base = rtrim( $base, '/' );
	$path = ltrim( $path, '/' );

	return '' === $path ? $base : $base . '/' . $path;
}

function plan_your_day_browser_default_options(): array {
	$defaults                              = \Acodebeard\PlanYourDay\Settings\Settings::defaults();
	$defaults['default_location_label']    = 'Test Harbor';
	$defaults['default_location_address']  = 'Test Harbor';
	$defaults['default_location_latitude'] = '37.7749';
	$defaults['default_location_longitude'] = '-122.4194';
	$defaults['map_preview_enabled']       = false;
	$defaults['maps_handoff_enabled']      = true;
	$defaults['categories']                = \Acodebeard\PlanYourDay\Settings\Settings::default_categories();

	return $defaults;
}

function plan_your_day_browser_seed_state(): array {
	return [
		'options'      => [
			\Acodebeard\PlanYourDay\Settings\Settings::OPTION_NAME => plan_your_day_browser_default_options(),
			'plan_your_day_schema_version'                         => PLAN_YOUR_DAY_SCHEMA_VERSION,
			'plan_your_day_version'                                => PLAN_YOUR_DAY_VERSION,
		],
		'transients'   => [],
		'object_cache' => [],
	];
}

function plan_your_day_browser_load_state(): void {
	$seed_state = plan_your_day_browser_seed_state();
	$state      = $seed_state;
	$state_file = plan_your_day_browser_state_file();

	if ( is_file( $state_file ) ) {
		$decoded = json_decode( (string) file_get_contents( $state_file ), true );

		if ( is_array( $decoded ) ) {
			$state = array_merge( $seed_state, $decoded );
			$state['options']      = is_array( $decoded['options'] ?? null ) ? array_merge( $seed_state['options'], $decoded['options'] ) : $seed_state['options'];
			$state['transients']   = is_array( $decoded['transients'] ?? null ) ? $decoded['transients'] : [];
			$state['object_cache'] = is_array( $decoded['object_cache'] ?? null ) ? $decoded['object_cache'] : [];
		}
	}

	$GLOBALS['plan_your_day_test_options']            = $state['options'];
	$GLOBALS['plan_your_day_test_transients']         = $state['transients'];
	$GLOBALS['plan_your_day_test_object_cache']       = $state['object_cache'];
	$GLOBALS['plan_your_day_use_ext_object_cache']    = false;
	$GLOBALS['plan_your_day_test_filters']            = [];
	$GLOBALS['plan_your_day_test_actions']            = [];
	$GLOBALS['plan_your_day_test_option_reads']       = [];
}

function plan_your_day_browser_persist_state(): void {
	$state_file = plan_your_day_browser_state_file();
	$directory  = dirname( $state_file );

	if ( ! is_dir( $directory ) ) {
		mkdir( $directory, 0777, true );
	}

	file_put_contents(
		$state_file,
		(string) wp_json_encode(
			[
				'options'      => $GLOBALS['plan_your_day_test_options'] ?? [],
				'transients'   => $GLOBALS['plan_your_day_test_transients'] ?? [],
				'object_cache' => $GLOBALS['plan_your_day_test_object_cache'] ?? [],
			]
		)
	);
}

function plan_your_day_browser_reset_state(): void {
	$state_file = plan_your_day_browser_state_file();

	if ( is_file( $state_file ) ) {
		unlink( $state_file );
	}

	plan_your_day_browser_load_state();
}

function plan_your_day_browser_render_styles(): string {
	$markup = '';

	foreach ( (array) ( $GLOBALS['plan_your_day_browser_enqueued_styles'] ?? [] ) as $style ) {
		$src = plan_your_day_browser_versioned_src( (string) ( $style['src'] ?? '' ), $style['ver'] ?? false );

		if ( '' === $src ) {
			continue;
		}

		$media  = (string) ( $style['media'] ?? 'all' );
		$markup .= sprintf(
			"<link rel=\"stylesheet\" href=\"%s\" media=\"%s\">\n",
			esc_url( $src ),
			esc_attr( $media )
		);
	}

	return $markup;
}

function plan_your_day_browser_render_scripts(): string {
	$markup = '';

	foreach ( (array) ( $GLOBALS['plan_your_day_browser_enqueued_scripts'] ?? [] ) as $script ) {
		$src = plan_your_day_browser_versioned_src( (string) ( $script['src'] ?? '' ), $script['ver'] ?? false );

		if ( '' === $src ) {
			continue;
		}

		$args       = is_array( $script['args'] ?? null ) ? $script['args'] : [];
		$attributes = [];

		if ( 'defer' === ( $args['strategy'] ?? '' ) ) {
			$attributes[] = 'defer';
		}

		$markup .= sprintf(
			"<script src=\"%s\"%s></script>\n",
			esc_url( $src ),
			[] === $attributes ? '' : ' ' . implode( ' ', $attributes )
		);
	}

	return $markup;
}

function plan_your_day_browser_versioned_src( string $src, string|bool|null $version ): string {
	$src = trim( $src );

	if ( '' === $src ) {
		return '';
	}

	if ( false === $version || null === $version || '' === $version ) {
		return $src;
	}

	return add_query_arg(
		[
			'ver' => (string) $version,
		],
		$src
	);
}
