<?php
/**
 * Install, schema, drop-in, capabilities.
 *
 * @package AiParseAble
 */

namespace AiParseAble;

use AiParseAble\Logger\Signatures;
use AiParseAble\Robots\PhysicalFile;

/**
 * Activation is for first install only. Upgrades go through Migrations on plugins_loaded, because the
 * activation hook never fires on a background update.
 */
final class Activation {

	const CAPABILITY = 'aiparseable_manage';

	/**
	 * Activation hook.
	 *
	 * @param bool $network_wide Network activation.
	 * @return void
	 */
	public static function activate( $network_wide = false ): void {
		$plugin = Plugin::instance();

		if ( is_multisite() && $network_wide ) {
			$sites = get_sites(
				array(
					'number' => 10000,
					'fields' => 'ids',
				)
			);
			foreach ( (array) $sites as $blog_id ) {
				switch_to_blog( (int) $blog_id );
				$plugin->options()->reset();
				try {
					self::install_site();
				} finally {
					restore_current_blog();
					$plugin->options()->reset();
				}
			}
		} else {
			self::install_site();
		}

		// Network-wide pieces, once.
		$plugin->drop_in()->install();
		$plugin->cron()->ensure_scheduled();

		// Fetch ranges soon after activation rather than waiting a week; the cron backend runs it.
		if ( ! wp_next_scheduled( 'ai_parseable_ranges_initial' ) ) {
			wp_schedule_single_event( time() + 30, Support\Cron::RANGES );
		}
	}

	/**
	 * Per-site install.
	 *
	 * @return void
	 */
	public static function install_site(): void {
		$plugin  = Plugin::instance();
		$options = $plugin->options();

		$plugin->repository()->install();
		update_option( Migrations::OPTION, Migrations::SCHEMA_VERSION, true );
		update_option( Migrations::OPTION_PLUGIN, AI_PARSEABLE_VERSION, true );
		Migrations::autoload_versions();

		$values = array(
			'ua_pattern'      => Signatures::compile(),
			'physical_robots' => PhysicalFile::exists(),
		);
		if ( 0 === (int) $options->get( 'installed_at', 0 ) ) {
			$values['installed_at'] = time();
		}
		$options->update( $values );
		$plugin->queue()->token();
		$plugin->queue()->ensure();

		self::grant_capability();
		Support\Rewrite::flush();
	}

	/**
	 * Grant the custom capability to administrators.
	 *
	 * @return void
	 */
	public static function grant_capability(): void {
		$role = get_role( 'administrator' );
		if ( $role && ! $role->has_cap( self::CAPABILITY ) ) {
			$role->add_cap( self::CAPABILITY );
		}
	}

	/**
	 * Deactivation: stop cron and remove the drop-in so nothing keeps writing while the plugin is off.
	 * Data and settings are kept; uninstall decides their fate.
	 *
	 * @return void
	 */
	public static function deactivate(): void {
		$plugin = Plugin::instance();
		$plugin->cron()->unschedule_all();
		$plugin->drop_in()->remove();
		wp_clear_scheduled_hook( Support\Cron::RANGES );
		Support\Rewrite::flush();
	}

	/**
	 * New site in the network.
	 *
	 * @param \WP_Site $site Site.
	 * @return void
	 */
	public static function on_initialize_site( $site ): void {
		if ( ! function_exists( 'is_plugin_active_for_network' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		if ( ! $site instanceof \WP_Site || ! is_plugin_active_for_network( plugin_basename( AI_PARSEABLE_FILE ) ) ) {
			return;
		}
		switch_to_blog( (int) $site->blog_id );
		Plugin::instance()->options()->reset();
		try {
			self::install_site();
		} finally {
			restore_current_blog();
			Plugin::instance()->options()->reset();
		}
	}

	/**
	 * Site removed from the network.
	 *
	 * @param \WP_Site $site Site.
	 * @return void
	 */
	public static function on_uninitialize_site( $site ): void {
		if ( ! $site instanceof \WP_Site ) {
			return;
		}
		switch_to_blog( (int) $site->blog_id );
		try {
			Plugin::instance()->repository()->drop();
		} finally {
			restore_current_blog();
		}
	}
}
