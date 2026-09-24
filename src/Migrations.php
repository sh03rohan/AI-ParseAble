<?php
/**
 * Versioned upgrades.
 *
 * @package CrawlLedger
 */

namespace CrawlLedger;

use CrawlLedger\Logger\Signatures;

/**
 * Two ladders: the schema version (tables) and the plugin version (per-release routines).
 * Every release has an entry in $releases, even a no-op, so the ladder is unbroken.
 */
final class Migrations {

	const SCHEMA_VERSION = 1;

	// Both are read on every request by maybe_run(), so they must be autoloaded: a non-autoloaded
	// option costs a query per request on sites without a persistent object cache.
	const OPTION        = 'crawlledger_schema_version';
	const OPTION_PLUGIN = 'crawlledger_plugin_version';

	/**
	 * Per-release routines, oldest first. Each callable runs once when upgrading past that version.
	 *
	 * @return array<string, callable>
	 */
	private static function releases(): array {
		return array(
			'0.1.0' => static function () {
				// Initial release: nothing to migrate.
			},
		);
	}

	/**
	 * Run on plugins_loaded when versions differ.
	 *
	 * @return void
	 */
	public static function maybe_run(): void {
		$schema  = (int) get_option( self::OPTION, 0 );
		$version = (string) get_option( self::OPTION_PLUGIN, '' );
		if ( $schema >= self::SCHEMA_VERSION && CRAWLLEDGER_VERSION === $version ) {
			return;
		}
		if ( is_multisite() && is_network_admin() ) {
			return; // Site-level tables are migrated in each site's own context.
		}
		self::to( self::SCHEMA_VERSION, $version );
	}

	/**
	 * Upgrade to a schema version and run release routines.
	 *
	 * @param int    $target       Schema version.
	 * @param string $from_version Previously installed plugin version.
	 * @return void
	 */
	public static function to( int $target, string $from_version = '' ): void {
		$plugin  = Plugin::instance();
		$current = (int) get_option( self::OPTION, 0 );

		if ( $current < $target ) {
			$plugin->repository()->install(); // dbDelta is idempotent.
			update_option( self::OPTION, $target, true );
		}

		foreach ( self::releases() as $version => $routine ) {
			if ( '' === $from_version || version_compare( $from_version, $version, '<' ) ) {
				$routine();
			}
		}

		// Things that must be refreshed on every version change.
		$plugin->options()->set( 'ua_pattern', Signatures::compile() );
		Activation::grant_capability();
		if ( ! is_multisite() || is_main_site() ) {
			$plugin->cron()->ensure_scheduled();
		}
		update_option( self::OPTION_PLUGIN, CRAWLLEDGER_VERSION, true );
		self::autoload_versions();
	}

	/**
	 * Make sure both version options are autoloaded; update_option() leaves the flag alone when the
	 * value is unchanged, so installs that stored them non-autoloaded are repaired here.
	 *
	 * @return void
	 */
	public static function autoload_versions(): void {
		$options = array(
			self::OPTION        => true,
			self::OPTION_PLUGIN => true,
		);
		if ( function_exists( 'wp_set_option_autoload_values' ) ) { // WordPress 6.4+.
			wp_set_option_autoload_values( $options );
			return;
		}
		foreach ( $options as $name => $autoload ) {
			$value = get_option( $name );
			delete_option( $name );
			add_option( $name, $value, '', $autoload );
		}
	}
}
