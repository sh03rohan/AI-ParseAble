<?php
/**
 * Plugin Name:       AI ParseAble – AI Crawler Log, GPTBot & ClaudeBot Control, llms.txt
 * Plugin URI:        https://github.com/sh03rohan/AI-ParseAble
 * Description:       AI crawler log and analytics for WordPress: see which AI bots (GPTBot, ClaudeBot, PerplexityBot) visit your site, verify they are real, allow or block them in robots.txt, fill schema gaps and serve llms.txt.
 * Version:           0.1.0
 * Requires at least: 6.4
 * Requires PHP:      7.4
 * Author:            AI ParseAble
 * Author URI:        https://aiparseable.com/
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       ai-parseable
 * Domain Path:       /languages
 *
 * @package AiParseAble
 */

// This file is bootstrap only: header, guards, autoloader, boot. No business logic.
// Keep the syntax here parseable by old PHP so the compatibility notice can render.

defined( 'ABSPATH' ) || exit;

define( 'AI_PARSEABLE_VERSION', '0.1.0' );
define( 'AI_PARSEABLE_FILE', __FILE__ );
define( 'AI_PARSEABLE_DIR', plugin_dir_path( __FILE__ ) );
define( 'AI_PARSEABLE_URL', plugin_dir_url( __FILE__ ) );

/**
 * Compatibility notice for unsupported PHP or WordPress versions.
 *
 * Never throws, never deactivates silently: a white screen is the fastest route to a one-star review.
 *
 * @return void
 */
function ai_parseable_compat_notice() {
	echo '<div class="notice notice-error"><p>';
	echo esc_html(
		sprintf(
			/* translators: 1: required PHP version, 2: required WordPress version */
			__( 'AI ParseAble requires PHP %1$s and WordPress %2$s or newer. The plugin is inactive until the server is updated.', 'ai-parseable' ),
			'7.4',
			'6.4'
		)
	);
	echo '</p></div>';
}

if ( version_compare( PHP_VERSION, '7.4', '<' ) || version_compare( get_bloginfo( 'version' ), '6.4', '<' ) ) {
	add_action( 'admin_notices', 'ai_parseable_compat_notice' );
	return;
}

// Autoload: prefer the build-time classmap; fall back to PSR-4 for development checkouts.
if ( is_readable( AI_PARSEABLE_DIR . 'vendor/autoload.php' ) ) {
	require AI_PARSEABLE_DIR . 'vendor/autoload.php';
} else {
	spl_autoload_register(
		function ( $class_name ) {
			if ( 0 !== strpos( $class_name, 'AiParseAble\\' ) ) {
				return;
			}
			$relative = str_replace( '\\', '/', substr( $class_name, strlen( 'AiParseAble\\' ) ) );
			$path     = AI_PARSEABLE_DIR . 'src/' . $relative . '.php';
			if ( is_file( $path ) ) {
				require $path;
			}
		}
	);
}

register_activation_hook( __FILE__, array( 'AiParseAble\\Activation', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'AiParseAble\\Activation', 'deactivate' ) );

AiParseAble\Plugin::boot();
