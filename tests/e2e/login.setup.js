// Log in once over HTTP and persist the cookies for every spec.
// Posting the form directly avoids wp-login.php's focus script, which can
// steal focus from a Playwright fill() and mix up the two fields.
const path = require( 'path' );
const { test: setup, expect } = require( '@playwright/test' );

setup( 'log in', async ( { request } ) => {
	await request.get( '/wp-login.php' ); // Sets wordpress_test_cookie.
	const res = await request.post( '/wp-login.php', {
		form: {
			log: process.env.WP_USERNAME || 'admin',
			pwd: process.env.WP_PASSWORD || 'password',
			testcookie: '1',
			'wp-submit': 'Log In',
			redirect_to: '/wp-admin/',
		},
	} );
	expect( res.url() ).toContain( '/wp-admin/' );
	await request.storageState( { path: path.join( __dirname, '.auth', 'state.json' ) } );
} );
