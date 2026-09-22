<?php
/**
 * Uninstall. Honours the "keep my data" setting, which defaults to keeping.
 *
 * @package AiParseAble
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

define( 'AI_PARSEABLE_VERSION', '0.1.0' );
define( 'AI_PARSEABLE_FILE', __DIR__ . '/ai-parseable.php' );
define( 'AI_PARSEABLE_DIR', plugin_dir_path( __FILE__ ) );
define( 'AI_PARSEABLE_URL', plugin_dir_url( __FILE__ ) );

if ( is_readable( AI_PARSEABLE_DIR . 'vendor/autoload.php' ) ) {
	require AI_PARSEABLE_DIR . 'vendor/autoload.php';
} else {
	spl_autoload_register(
		function ( $class_name ) {
			if ( 0 !== strpos( $class_name, 'AiParseAble\\' ) ) {
				return;
			}
			$path = AI_PARSEABLE_DIR . 'src/' . str_replace( '\\', '/', substr( $class_name, 12 ) ) . '.php';
			if ( is_file( $path ) ) {
				require $path;
			}
		}
	);
}

/**
 * Remove everything for the current site.
 *
 * @return void
 */
function ai_parseable_uninstall_site() {
	$plugin  = AiParseAble\Plugin::instance();
	$options = $plugin->options();
	$options->reset();

	if ( $options->get( 'keep_data_on_uninstall', true ) ) {
		return;
	}

	$plugin->repository()->drop();
	delete_option( AiParseAble\Support\Options::OPTION );
	delete_option( AiParseAble\Migrations::OPTION );
	delete_option( AiParseAble\Migrations::OPTION_PLUGIN );
	delete_option( AiParseAble\Logger\Ranges::OPTION );
	delete_option( AiParseAble\Logger\Ingest::OPTION_LAST );
	delete_option( AiParseAble\Logger\Ingest::OPTION_TIMING );
	delete_option( 'ai_parseable_lock_ai_parseable_ingest_lock' );
	delete_option( 'ai_parseable_lock_ai_parseable_rollup_lock' );

	global $wpdb;
	$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\\_transient\\_ai\\_parseable\\_%' OR option_name LIKE '\\_transient\\_timeout\\_ai\\_parseable\\_%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$wpdb->query( "DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE 'ai\\_parseable\\_%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

	$role = get_role( 'administrator' );
	if ( $role ) {
		$role->remove_cap( AiParseAble\Activation::CAPABILITY );
	}
	foreach ( wp_roles()->role_objects as $r ) {
		if ( $r->has_cap( AiParseAble\Activation::CAPABILITY ) ) {
			$r->remove_cap( AiParseAble\Activation::CAPABILITY );
		}
	}
}

$ai_parseable_plugin = AiParseAble\Plugin::instance();
$ai_parseable_plugin->cron()->unschedule_all();

if ( is_multisite() ) {
	$ai_parseable_sites = get_sites(
		array(
			'number' => 10000,
			'fields' => 'ids',
		)
	);
	$ai_parseable_wipe  = false;
	foreach ( (array) $ai_parseable_sites as $ai_parseable_blog_id ) {
		switch_to_blog( (int) $ai_parseable_blog_id );
		$ai_parseable_plugin->options()->reset();
		try {
			if ( ! $ai_parseable_plugin->options()->get( 'keep_data_on_uninstall', true ) ) {
				$ai_parseable_wipe = true;
			}
			ai_parseable_uninstall_site();
		} finally {
			restore_current_blog();
		}
	}
	if ( $ai_parseable_wipe ) {
		$ai_parseable_plugin->queue()->remove_all();
	}
} else {
	$ai_parseable_keep = $ai_parseable_plugin->options()->get( 'keep_data_on_uninstall', true );
	ai_parseable_uninstall_site();
	if ( ! $ai_parseable_keep ) {
		$ai_parseable_plugin->queue()->remove_all();
	}
}
