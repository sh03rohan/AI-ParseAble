<?php
/**
 * Drop-in lifecycle.
 *
 * @package AiParseAble
 */

namespace AiParseAble\Logger;

use AiParseAble\Support\Options;

/**
 * Installs the mu-plugin drop-in, verifies it on admin loads, refreshes it on upgrade, removes it on uninstall.
 *
 * Coverage modes reported to the UI:
 *  - 'drop-in'   : the mu-plugin is in place and current.
 *  - 'php'       : mu-plugins is not writable; PHP-level logging only, page-cache hits are missed.
 */
final class DropIn {

	const FILENAME  = 'ai-parseable-drop-in.php';
	const MODE_DROP = 'drop-in';
	const MODE_PHP  = 'php';

	/**
	 * The template line that receives the generated config.
	 */
	const CONFIG_PLACEHOLDER = '$ai_parseable_config = array(); // Replaced by the plugin: AI_PARSEABLE_CONFIG.';

	/**
	 * Settings.
	 *
	 * @var Options
	 */
	private $options;

	/**
	 * Queue helper.
	 *
	 * @var Queue
	 */
	private $queue;

	/**
	 * Constructor.
	 *
	 * @param Options $options Settings.
	 * @param Queue   $queue   Queue helper.
	 */
	public function __construct( Options $options, Queue $queue ) {
		$this->options = $options;
		$this->queue   = $queue;
	}

	/**
	 * Target path in mu-plugins.
	 *
	 * @return string
	 */
	public function path(): string {
		return trailingslashit( WPMU_PLUGIN_DIR ) . self::FILENAME;
	}

	/**
	 * Version string that identifies this build of the drop-in: plugin version + hash of its configuration.
	 *
	 * @return string
	 */
	public function expected_version(): string {
		static $template_hash = null;
		if ( null === $template_hash ) {
			$template_hash = (string) md5_file( AI_PARSEABLE_DIR . 'mu/' . self::FILENAME );
		}
		return AI_PARSEABLE_VERSION . '+' . substr( md5( $template_hash . wp_json_encode( $this->config() ) ), 0, 8 );
	}

	/**
	 * Configuration baked into the file.
	 *
	 * @return array<mixed>
	 */
	private function config(): array {
		return array(
			'pattern'   => (string) $this->options->get( 'ua_pattern', '' ),
			'dir'       => $this->queue->dir(),
			'token'     => $this->queue->token(),
			'max_bytes' => Queue::MAX_FILE_BYTES,
			'plugin'    => AI_PARSEABLE_FILE,
		);
	}

	/**
	 * Render the drop-in source from the template.
	 *
	 * @return string
	 */
	public function render(): string {
		$template = (string) file_get_contents( AI_PARSEABLE_DIR . 'mu/' . self::FILENAME ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- plugin's own file.
		return self::fill( $template, $this->expected_version(), $this->config() );
	}

	/**
	 * Substitute the version and config into the template source.
	 *
	 * Plain string replacement only: the config holds a regex, and a preg_replace() replacement string
	 * would reinterpret its backslashes and "$n" sequences and undo var_export()'s escaping.
	 *
	 * @param string       $template Template source.
	 * @param string       $version  Drop-in version string.
	 * @param array<mixed> $config   Config array to embed.
	 * @return string
	 */
	public static function fill( string $template, string $version, array $config ): string {
		$exported = var_export( $config, true ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_var_export -- generating source, not debugging.

		$source = str_replace( "define( 'AI_PARSEABLE_DROP_IN', '0.0.0' );", "define( 'AI_PARSEABLE_DROP_IN', '" . $version . "' );", $template );
		$source = str_replace( ' * Version:     0.0.0', ' * Version:     ' . $version, $source );
		$source = str_replace( ' * Drop-in Name: ', ' * Plugin Name: ', $source );
		return str_replace( self::CONFIG_PLACEHOLDER, '$ai_parseable_config = ' . $exported . ';', $source );
	}

	/**
	 * Install or refresh. Never fatal: returns false and records why.
	 *
	 * @return bool
	 */
	public function install(): bool {
		if ( ! $this->queue->ensure() ) {
			$this->options->update(
				array(
					'drop_in_version' => '',
					'drop_in_notice'  => 'queue-unwritable',
				)
			);
			return false;
		}

		$dir = WPMU_PLUGIN_DIR;
		if ( ! is_dir( $dir ) && ! wp_mkdir_p( $dir ) ) {
			$this->options->update(
				array(
					'drop_in_version' => '',
					'drop_in_notice'  => 'mu-unwritable',
				)
			);
			return false;
		}
		if ( ! wp_is_writable( $dir ) ) {
			$this->options->update(
				array(
					'drop_in_version' => '',
					'drop_in_notice'  => 'mu-unwritable',
				)
			);
			return false;
		}

		$source = $this->render();
		$tmp    = $this->path() . '.tmp';
		if ( false === @file_put_contents( $tmp, $source, LOCK_EX ) || ! @rename( $tmp, $this->path() ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged,WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents,WordPress.WP.AlternativeFunctions.rename_rename -- write-then-rename so a half-written drop-in is never loaded.
			@unlink( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged,WordPress.WP.AlternativeFunctions.unlink_unlink -- temp file we just created.
			$this->options->update(
				array(
					'drop_in_version' => '',
					'drop_in_notice'  => 'mu-unwritable',
				)
			);
			return false;
		}

		$this->options->update(
			array(
				'drop_in_version' => $this->expected_version(),
				'drop_in_notice'  => '',
			)
		);
		delete_transient( 'ai_parseable_drop_in_check' );
		return true;
	}

	/**
	 * Remove the file.
	 *
	 * @return void
	 */
	public function remove(): void {
		if ( is_file( $this->path() ) ) {
			wp_delete_file( $this->path() );
		}
		$this->options->update(
			array(
				'drop_in_version' => '',
				'drop_in_notice'  => '',
			)
		);
		delete_transient( 'ai_parseable_drop_in_check' );
	}

	/**
	 * Whether the installed file exists and matches the expected version.
	 *
	 * @return bool
	 */
	public function is_current(): bool {
		$path = $this->path();
		if ( ! is_file( $path ) ) {
			return false;
		}
		$head = (string) file_get_contents( $path, false, null, 0, 2048 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		return false !== strpos( $head, "define( 'AI_PARSEABLE_DROP_IN', '" . $this->expected_version() . "' );" );
	}

	/**
	 * Hourly-cached health check. Self-heals when the host wiped mu-plugins.
	 *
	 * @return bool True when the drop-in is in place after the check.
	 */
	public function verify(): bool {
		$cached = get_transient( 'ai_parseable_drop_in_check' );
		if ( 'ok' === $cached ) {
			return true;
		}
		if ( $this->is_current() ) {
			set_transient( 'ai_parseable_drop_in_check', 'ok', HOUR_IN_SECONDS );
			return true;
		}
		$ok = $this->install();
		set_transient( 'ai_parseable_drop_in_check', $ok ? 'ok' : 'missing', HOUR_IN_SECONDS );
		return $ok;
	}

	/**
	 * Active coverage mode.
	 *
	 * @return string
	 */
	public function mode(): string {
		return $this->is_current() ? self::MODE_DROP : self::MODE_PHP;
	}

	/**
	 * Describe what the current setup can and cannot see. Honest coverage is a feature.
	 *
	 * @return array<mixed>
	 */
	public function coverage(): array {
		$mode       = $this->mode();
		$page_cache = $this->detect_page_cache();
		$notice     = (string) $this->options->get( 'drop_in_notice', '' );

		$misses = array();
		if ( self::MODE_PHP === $mode ) {
			$misses[] = __( 'Requests served by a page cache before WordPress plugins load.', 'ai-parseable' );
		}
		if ( $page_cache['advanced_cache'] ) {
			$misses[] = __( 'Pages served directly by advanced-cache.php (it runs before mu-plugins).', 'ai-parseable' );
		}
		if ( $page_cache['server_level'] ) {
			$misses[] = __( 'Pages served by a server-level or CDN cache (LiteSpeed, Nginx FastCGI cache, Cloudflare APO). Those never reach PHP; use server-log ingestion for full coverage.', 'ai-parseable' );
		}

		return array(
			'mode'         => $mode,
			'notice'       => $notice,
			'drop_in_path' => $this->path(),
			'page_cache'   => $page_cache,
			'misses'       => $misses,
			'complete'     => self::MODE_DROP === $mode && ! $page_cache['advanced_cache'] && ! $page_cache['server_level'],
		);
	}

	/**
	 * Detect caching layers that bypass PHP.
	 *
	 * @return array<mixed>
	 */
	private function detect_page_cache(): array {
		$advanced = defined( 'WP_CACHE' ) && WP_CACHE && is_file( WP_CONTENT_DIR . '/advanced-cache.php' );
		$plugins  = array();
		$server   = false;

		$known  = array(
			'wp-rocket/wp-rocket.php'             => 'WP Rocket',
			'litespeed-cache/litespeed-cache.php' => 'LiteSpeed Cache',
			'w3-total-cache/w3-total-cache.php'   => 'W3 Total Cache',
			'wp-super-cache/wp-cache.php'         => 'WP Super Cache',
			'wp-fastest-cache/wpFastestCache.php' => 'WP Fastest Cache',
			'cache-enabler/cache-enabler.php'     => 'Cache Enabler',
			'nginx-helper/nginx-helper.php'       => 'Nginx Helper',
			'cloudflare/cloudflare.php'           => 'Cloudflare',
			'sg-cachepress/sg-cachepress.php'     => 'SiteGround Optimizer',
			'breeze/breeze.php'                   => 'Breeze',
		);
		$active = (array) get_option( 'active_plugins', array() );
		if ( is_multisite() ) {
			$active = array_merge( $active, array_keys( (array) get_site_option( 'active_sitewide_plugins', array() ) ) );
		}
		foreach ( $known as $file => $label ) {
			if ( in_array( $file, $active, true ) ) {
				$plugins[] = $label;
				if ( in_array( $file, array( 'litespeed-cache/litespeed-cache.php', 'nginx-helper/nginx-helper.php', 'cloudflare/cloudflare.php', 'sg-cachepress/sg-cachepress.php' ), true ) ) {
					$server = true;
				}
			}
		}
		if ( isset( $_SERVER['SERVER_SOFTWARE'] ) && false !== stripos( (string) $_SERVER['SERVER_SOFTWARE'], 'litespeed' ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			$server = true;
		}

		return array(
			'advanced_cache' => $advanced,
			'server_level'   => $server,
			'plugins'        => $plugins,
		);
	}
}
