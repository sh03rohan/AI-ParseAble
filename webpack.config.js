/**
 * Two bundles: the admin app and the editor panel. Both use core's @wordpress/* packages as externals
 * (handled by @wordpress/scripts' DependencyExtractionWebpackPlugin) so nothing ships twice.
 */
const path = require( 'path' );
const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );

module.exports = {
	...defaultConfig,
	entry: {
		admin: path.resolve( __dirname, 'ui/admin/index.js' ),
		editor: path.resolve( __dirname, 'ui/editor/index.js' ),
	},
	output: {
		...defaultConfig.output,
		path: path.resolve( __dirname, 'assets' ),
		filename: '[name].js',
	},
};
