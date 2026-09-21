<?php
/**
 * Rewrite-rule refresh that is safe in every context.
 *
 * @package AiParseAble
 */

namespace AiParseAble\Support;

/**
 * Core's flush_rewrite_rules() regenerates from the global $wp_rewrite, which was initialised for the request's
 * own site. Inside switch_to_blog() or WP-CLI that state belongs to another site or context, and the
 * result silently drops rules (core's robots.txt one included). Core's remedy is to delete the option and
 * let the site regenerate it lazily on its next request — so that is what this does there.
 */
final class Rewrite {

	/**
	 * Refresh rewrite rules for the current site.
	 *
	 * @return void
	 */
	public static function flush(): void {
		$switched = is_multisite() && function_exists( 'ms_is_switched' ) && ms_is_switched();
		$cli      = defined( 'WP_CLI' ) && WP_CLI;
		if ( $switched || $cli ) {
			delete_option( 'rewrite_rules' );
			return;
		}
		flush_rewrite_rules( false );
	}
}
