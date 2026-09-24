<?php
/**
 * Plugin Name:       CrawlLedger – AI Crawler Log & Access Control for GPTBot and ClaudeBot
 * Plugin URI:        https://github.com/sh03rohan/CrawlLedger
 * Description:       AI crawler log and analytics for WordPress: see which AI bots (GPTBot, ClaudeBot, PerplexityBot) visit your site, verify they are real, allow or block them in robots.txt, fill schema gaps and serve llms.txt.
 * Version:           0.1.0
 * Requires at least: 6.4
 * Requires PHP:      7.4
 * Author:            Sakibul Hasan Rohan
 * Author URI:        https://github.com/sh03rohan
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       crawlledger-ai-crawler-log
 * Domain Path:       /languages
 *
 * @package CrawlLedger
 */

// This file is bootstrap only: header, guards, autoloader, boot. No business logic.
// Keep the syntax here parseable by old PHP so the compatibility notice can render.

defined( 'ABSPATH' ) || exit;

define( 'CRAWLLEDGER_VERSION', '0.1.0' );
define( 'CRAWLLEDGER_FILE', __FILE__ );
define( 'CRAWLLEDGER_DIR', plugin_dir_path( __FILE__ ) );
define( 'CRAWLLEDGER_URL', plugin_dir_url( __FILE__ ) );

/**
 * Compatibility notice for unsupported PHP or WordPress versions.
 *
 * Never throws, never deactivates silently: a white screen is the fastest route to a one-star review.
 *
 * @return void
 */
function crawlledger_compat_notice() {
	echo '<div class="notice notice-error"><p>';
	echo esc_html(
		sprintf(
			/* translators: 1: required PHP version, 2: required WordPress version */
			__( 'CrawlLedger requires PHP %1$s and WordPress %2$s or newer. The plugin is inactive until the server is updated.', 'crawlledger-ai-crawler-log' ),
			'7.4',
			'6.4'
		)
	);
	echo '</p></div>';
}

if ( version_compare( PHP_VERSION, '7.4', '<' ) || version_compare( get_bloginfo( 'version' ), '6.4', '<' ) ) {
	add_action( 'admin_notices', 'crawlledger_compat_notice' );
	return;
}

// Autoload: prefer the build-time classmap; fall back to PSR-4 for development checkouts.
if ( is_readable( CRAWLLEDGER_DIR . 'vendor/autoload.php' ) ) {
	require CRAWLLEDGER_DIR . 'vendor/autoload.php';
} else {
	spl_autoload_register(
		function ( $class_name ) {
			if ( 0 !== strpos( $class_name, 'CrawlLedger\\' ) ) {
				return;
			}
			$relative = str_replace( '\\', '/', substr( $class_name, strlen( 'CrawlLedger\\' ) ) );
			$path     = CRAWLLEDGER_DIR . 'src/' . $relative . '.php';
			if ( is_file( $path ) ) {
				require $path;
			}
		}
	);
}

register_activation_hook( __FILE__, array( 'CrawlLedger\\Activation', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'CrawlLedger\\Activation', 'deactivate' ) );

CrawlLedger\Plugin::boot();
