<?php
/**
 * Script registration helpers.
 *
 * @package AiParseAble
 */

namespace AiParseAble\Support;

/**
 * Bundles built with @wordpress/scripts depend on the "react-jsx-runtime" handle, which WordPress
 * registers from 6.6. On 6.4 and 6.5 the plugin registers its own copy of the same React build.
 */
final class Assets {

	/**
	 * Register the JSX runtime shim when core has not registered the handle.
	 *
	 * @return void
	 */
	public static function ensure_jsx_runtime(): void {
		if ( wp_script_is( 'react-jsx-runtime', 'registered' ) ) {
			return;
		}
		$asset_file = AI_PARSEABLE_DIR . 'assets/react-jsx-runtime.asset.php';
		if ( ! is_readable( $asset_file ) ) {
			return;
		}
		$asset = require $asset_file;
		wp_register_script( 'react-jsx-runtime', AI_PARSEABLE_URL . 'assets/react-jsx-runtime.js', $asset['dependencies'], $asset['version'], true );
	}
}
