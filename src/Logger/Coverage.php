<?php
/**
 * Coverage report: what this install can and cannot see.
 *
 * @package CrawlLedger
 */

namespace CrawlLedger\Logger;

/**
 * The collector runs when the plugin file is included, before any hook fires. What it can never see are
 * responses that a page cache or CDN serves before WordPress runs at all. Saying so plainly on the
 * dashboard is a feature: silence must never be mistaken for "no AI traffic".
 */
final class Coverage {

	/**
	 * Describe what the current setup can and cannot see.
	 *
	 * @return array<mixed>
	 */
	public function report(): array {
		$page_cache = $this->detect_page_cache();

		$misses = array();
		if ( $page_cache['advanced_cache'] ) {
			$misses[] = __( 'Pages served directly by advanced-cache.php (it runs before any plugin).', 'crawlledger-ai-crawler-log' );
		}
		if ( $page_cache['server_level'] ) {
			$misses[] = __( 'Pages served by a server-level or CDN cache (LiteSpeed, Nginx FastCGI cache, Cloudflare APO). Those never reach PHP; use server-log ingestion for full coverage.', 'crawlledger-ai-crawler-log' );
		}

		return array(
			'page_cache' => $page_cache,
			'misses'     => $misses,
			'complete'   => ! $page_cache['advanced_cache'] && ! $page_cache['server_level'],
		);
	}

	/**
	 * Detect caching layers that bypass PHP.
	 *
	 * @return array<mixed>
	 */
	public function detect_page_cache(): array {
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
