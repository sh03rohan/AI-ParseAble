<?php
/**
 * Block-editor clarity checks.
 *
 * @package AiParseAble
 */

namespace AiParseAble\Editor;

use AiParseAble\Module;
use AiParseAble\Support\Assets;

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
		$asset_file = AI_PARSEABLE_DIR . 'assets/editor.asset.php';
		if ( ! is_readable( $asset_file ) || ! is_readable( AI_PARSEABLE_DIR . 'assets/editor.js' ) ) {
			return;
		}
		$asset = require $asset_file;
		Assets::ensure_jsx_runtime();
		wp_enqueue_script( 'ai-parseable-editor', AI_PARSEABLE_URL . 'assets/editor.js', $asset['dependencies'], $asset['version'], true );
		wp_set_script_translations( 'ai-parseable-editor', 'ai-parseable', AI_PARSEABLE_DIR . 'languages' );
		if ( is_readable( AI_PARSEABLE_DIR . 'assets/editor.css' ) ) {
			wp_enqueue_style( 'ai-parseable-editor', AI_PARSEABLE_URL . 'assets/editor.css', array(), $asset['version'] );
		}
	}
}
