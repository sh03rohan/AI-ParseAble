const { test, expect } = require( '@playwright/test' );

test( 'dashboard loads and the overview renders', async ( { page } ) => {
	// Collect JS errors from the first byte so a failed mount reports its cause, not just a missing element.
	const errors = [];
	page.on( 'pageerror', ( e ) => errors.push( e.message ) );
	page.on( 'console', ( m ) => {
		if ( m.type() === 'error' ) {
			errors.push( m.text() );
		}
	} );
	const response = await page.goto( '/wp-admin/admin.php?page=crawlledger' );
	expect( response.status(), 'admin page HTTP status' ).toBe( 200 );
	try {
		await expect( page.locator( '.clg-status' ) ).toBeVisible( { timeout: 15000 } );
	} catch ( e ) {
		throw new Error( `App did not render. JS errors: ${ errors.join( ' | ' ) || 'none' }. Body: ${ ( await page.locator( 'body' ).innerText() ).slice( 0, 800 ) }` );
	}
	// A fresh install shows the empty state; a site with data shows the chart. Either is a successful paint.
	await expect( page.locator( 'svg.clg-chart, .clg-empty' ).first() ).toBeVisible();
	await page.waitForTimeout( 500 );
	expect( errors ).toEqual( [] );
} );

// Set a crawler's rule through the UI and wait for the save to be confirmed.
async function setRule( page, bot, rule ) {
	const row = page.locator( `tr:has-text("${ bot }")` ).first();
	await row.locator( `.clg-segmented button:has-text("${ rule }")` ).click();
	await expect( page.locator( '.clg-savebar.is-dirty' ) ).toBeVisible();
	await page.click( '.clg-savebar button:has-text("Save rules")' );
	await expect( page.locator( '.clg-savebar' ) ).toContainText( 'Rules saved' );
}

test( 'a crawler rule persists and reaches robots.txt', async ( { page } ) => {
	await page.goto( '/wp-admin/admin.php?page=crawlledger#crawlers' );
	await page.waitForSelector( '.clg-rules' );
	const active = page.locator( 'tr:has-text("GPTBot")' ).first().locator( '.clg-segmented button.is-active' );

	// The site may already block GPTBot; start from Allow so the change below is a real one.
	if ( ( await active.textContent() ) === 'Block' ) {
		await setRule( page, 'GPTBot', 'Allow' );
	}
	await setRule( page, 'GPTBot', 'Block' );

	await page.reload();
	await page.waitForSelector( '.clg-rules' );
	await expect( active ).toHaveText( 'Block' );

	const robots = await page.request.get( '/robots.txt' );
	expect( await robots.text() ).toContain( 'User-agent: GPTBot\nDisallow: /' );
} );

test( 'llms.txt preview follows the summary and is served once saved', async ( { page } ) => {
	// A unique summary so the editor is dirty even when a previous run saved this test's text.
	const summary = `Example Co sells widgets to 40 countries from its factory in Leeds (${ Date.now() }).`;
	await page.goto( '/wp-admin/admin.php?page=crawlledger#llms' );
	await page.waitForSelector( 'textarea' );
	await page.fill( 'textarea', summary );
	await expect( page.locator( '.clg-pre' ) ).toContainText( summary );
	await page.click( '.clg-savebar button:has-text("Save")' );
	await expect( page.locator( '.clg-savebar' ) ).toContainText( 'Saved' );
	const llms = await page.request.get( '/llms.txt' );
	expect( llms.status() ).toBe( 200 );
	expect( await llms.text() ).toContain( `> ${ summary }` );
} );
