<?php
/**
 * PHPUnit bootstrap.
 *
 * Unit suite: no WordPress, a handful of function stubs so the pure classes load.
 * Integration suite: set WP_TESTS_DIR (wp-env does) to load the core test framework instead.
 *
 * @package AiParseAble
 */

$ai_parseable_tests_dir = getenv( 'WP_TESTS_DIR' );
$ai_parseable_suite     = getenv( 'AI_PARSEABLE_SUITE' );

if ( $ai_parseable_tests_dir && 'unit' !== $ai_parseable_suite ) {
	require_once $ai_parseable_tests_dir . '/includes/functions.php';
	tests_add_filter(
		'muplugins_loaded',
		static function () {
			require dirname( __DIR__ ) . '/ai-parseable.php';
		}
	);
	require $ai_parseable_tests_dir . '/includes/bootstrap.php';
	return;
}

define( 'ABSPATH', __DIR__ . '/fixtures/abspath/' );
define( 'AI_PARSEABLE_VERSION', '0.1.0' );
define( 'AI_PARSEABLE_FILE', dirname( __DIR__ ) . '/ai-parseable.php' );
define( 'AI_PARSEABLE_DIR', dirname( __DIR__ ) . '/' );
define( 'AI_PARSEABLE_URL', 'https://example.test/wp-content/plugins/ai-parseable/' );
define( 'DAY_IN_SECONDS', 86400 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'WEEK_IN_SECONDS', 604800 );

spl_autoload_register(
	static function ( $class_name ) {
		if ( 0 !== strpos( $class_name, 'AiParseAble\\' ) ) {
			return;
		}
		$path = dirname( __DIR__ ) . '/src/' . str_replace( '\\', '/', substr( $class_name, 12 ) ) . '.php';
		if ( is_file( $path ) ) {
			require $path;
		}
	}
);

// Minimal WordPress stubs. Only what the pure classes touch.
$GLOBALS['ai_parseable_transients'] = array();
$GLOBALS['ai_parseable_filters']    = array();

if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( $hook, $value ) { // phpcs:ignore
		if ( isset( $GLOBALS['ai_parseable_filters'][ $hook ] ) ) {
			foreach ( $GLOBALS['ai_parseable_filters'][ $hook ] as $cb ) {
				$value = $cb( $value );
			}
		}
		return $value;
	}
	function add_filter( $hook, $cb ) { // phpcs:ignore
		$GLOBALS['ai_parseable_filters'][ $hook ][] = $cb;
	}
	function add_action( $hook, $cb ) { // phpcs:ignore
		add_filter( $hook, $cb );
	}
	function __( $text ) { return $text; } // phpcs:ignore
	function esc_html( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES ); } // phpcs:ignore
	function wp_parse_url( $url, $component = -1 ) { return parse_url( $url, $component ); } // phpcs:ignore
	function wp_using_ext_object_cache() { return false; } // phpcs:ignore
	function get_transient( $key ) { return $GLOBALS['ai_parseable_transients'][ $key ] ?? false; } // phpcs:ignore
	function set_transient( $key, $value ) { $GLOBALS['ai_parseable_transients'][ $key ] = $value; return true; } // phpcs:ignore
	function delete_transient( $key ) { unset( $GLOBALS['ai_parseable_transients'][ $key ] ); return true; } // phpcs:ignore
	function get_option( $key, $default = false ) { return $GLOBALS['ai_parseable_options'][ $key ] ?? $default; } // phpcs:ignore
	function update_option( $key, $value ) { $GLOBALS['ai_parseable_options'][ $key ] = $value; return true; } // phpcs:ignore
	function wp_json_encode( $data, $flags = 0 ) { return json_encode( $data, $flags ); } // phpcs:ignore
	function sanitize_key( $key ) { return strtolower( preg_replace( '/[^a-z0-9_\-]/i', '', (string) $key ) ); } // phpcs:ignore
	function wp_strip_all_tags( $s ) { return trim( strip_tags( (string) $s ) ); } // phpcs:ignore
	function trailingslashit( $s ) { return rtrim( $s, '/\\' ) . '/'; } // phpcs:ignore
	function wp_is_writable( $p ) { return is_writable( $p ); } // phpcs:ignore
	function is_wp_error( $t ) { return $t instanceof WP_Error; } // phpcs:ignore
	class WP_Error { // phpcs:ignore
		public $code; public $message; // phpcs:ignore
		public function __construct( $code = '', $message = '' ) { $this->code = $code; $this->message = $message; } // phpcs:ignore
		public function get_error_message() { return $this->message; } // phpcs:ignore
		public function get_error_code() { return $this->code; } // phpcs:ignore
	}
}
