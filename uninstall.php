<?php
/**
 * Uninstall. Honours the "keep my data" setting, which defaults to keeping.
 *
 * @package CrawlLedger
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

define( 'CRAWLLEDGER_VERSION', '0.1.0' );
define( 'CRAWLLEDGER_FILE', __DIR__ . '/crawlledger.php' );
define( 'CRAWLLEDGER_DIR', plugin_dir_path( __FILE__ ) );
define( 'CRAWLLEDGER_URL', plugin_dir_url( __FILE__ ) );

if ( is_readable( CRAWLLEDGER_DIR . 'vendor/autoload.php' ) ) {
	require CRAWLLEDGER_DIR . 'vendor/autoload.php';
} else {
	spl_autoload_register(
		function ( $class_name ) {
			if ( 0 !== strpos( $class_name, 'CrawlLedger\\' ) ) {
				return;
			}
			$path = CRAWLLEDGER_DIR . 'src/' . str_replace( '\\', '/', substr( $class_name, 12 ) ) . '.php';
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
function crawlledger_uninstall_site() {
	$plugin  = CrawlLedger\Plugin::instance();
	$options = $plugin->options();
	$options->reset();

	if ( $options->get( 'keep_data_on_uninstall', true ) ) {
		return;
	}

	$plugin->repository()->drop();
	delete_option( CrawlLedger\Support\Options::OPTION );
	delete_option( CrawlLedger\Migrations::OPTION );
	delete_option( CrawlLedger\Migrations::OPTION_PLUGIN );
	delete_option( CrawlLedger\Logger\Ranges::OPTION );
	delete_option( CrawlLedger\Logger\Ingest::OPTION_LAST );
	delete_option( CrawlLedger\Logger\Ingest::OPTION_TIMING );
	delete_option( 'crawlledger_lock_crawlledger_ingest_lock' );
	delete_option( 'crawlledger_lock_crawlledger_rollup_lock' );

	global $wpdb;
	$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\\_transient\\_crawlledger\\_%' OR option_name LIKE '\\_transient\\_timeout\\_crawlledger\\_%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$wpdb->query( "DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE 'crawlledger\\_%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

	$role = get_role( 'administrator' );
	if ( $role ) {
		$role->remove_cap( CrawlLedger\Activation::CAPABILITY );
	}
	foreach ( wp_roles()->role_objects as $r ) {
		if ( $r->has_cap( CrawlLedger\Activation::CAPABILITY ) ) {
			$r->remove_cap( CrawlLedger\Activation::CAPABILITY );
		}
	}
}

$crawlledger_plugin = CrawlLedger\Plugin::instance();
$crawlledger_plugin->cron()->unschedule_all();

if ( is_multisite() ) {
	$crawlledger_sites = get_sites(
		array(
			'number' => 10000,
			'fields' => 'ids',
		)
	);
	$crawlledger_wipe  = false;
	foreach ( (array) $crawlledger_sites as $crawlledger_blog_id ) {
		switch_to_blog( (int) $crawlledger_blog_id );
		$crawlledger_plugin->options()->reset();
		try {
			if ( ! $crawlledger_plugin->options()->get( 'keep_data_on_uninstall', true ) ) {
				$crawlledger_wipe = true;
			}
			crawlledger_uninstall_site();
		} finally {
			restore_current_blog();
		}
	}
	if ( $crawlledger_wipe ) {
		$crawlledger_plugin->queue()->remove_all();
	}
} else {
	$crawlledger_keep = $crawlledger_plugin->options()->get( 'keep_data_on_uninstall', true );
	crawlledger_uninstall_site();
	if ( ! $crawlledger_keep ) {
		$crawlledger_plugin->queue()->remove_all();
	}
}
