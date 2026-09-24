<?php
/**
 * Block-editor clarity checks.
 *
 * @package CrawlLedger
 */

namespace CrawlLedger\Editor;

use CrawlLedger\Module;
use CrawlLedger\Support\Assets;

/**
 * A separate small bundle loaded only on enqueue_block_editor_assets. Checks run client-side.
 */
final class Editor implements Module {

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'enqueue_block_editor_assets', array( $this, 'assets' ) );
	}

	/**
	 * Enqueue.
	 *
	 * @return void
	 */
	public function assets(): void {
		$asset_file = CRAWLLEDGER_DIR . 'assets/editor.asset.php';
		if ( ! is_readable( $asset_file ) || ! is_readable( CRAWLLEDGER_DIR . 'assets/editor.js' ) ) {
			return;
		}
		$asset = require $asset_file;
		Assets::ensure_jsx_runtime();
		wp_enqueue_script( 'crawlledger-editor', CRAWLLEDGER_URL . 'assets/editor.js', $asset['dependencies'], $asset['version'], true );
		wp_set_script_translations( 'crawlledger-editor', 'crawlledger-ai-crawler-log', CRAWLLEDGER_DIR . 'languages' );
		if ( is_readable( CRAWLLEDGER_DIR . 'assets/editor.css' ) ) {
			wp_enqueue_style( 'crawlledger-editor', CRAWLLEDGER_URL . 'assets/editor.css', array(), $asset['version'] );
		}
	}
}
