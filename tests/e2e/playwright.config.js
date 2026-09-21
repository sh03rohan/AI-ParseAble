// Playwright against wp-env (http://localhost:8888, admin/password) by default.
// Override with WP_BASE_URL / WP_USERNAME / WP_PASSWORD to run against any site.
const path = require( 'path' );
const { defineConfig } = require( '@playwright/test' );

module.exports = defineConfig( {
	testDir: __dirname,
	timeout: 60000,
	use: {
		baseURL: process.env.WP_BASE_URL || 'http://localhost:8888',
		ignoreHTTPSErrors: true,
		trace: 'retain-on-failure',
	},
	projects: [
		{ name: 'login', testMatch: /login\.setup\.js/ },
		{
			name: 'chromium',
			dependencies: [ 'login' ],
			testMatch: /.*\.spec\.js/,
			use: { storageState: path.join( __dirname, '.auth', 'state.json' ) },
		},
	],
} );
