const { test, expect } = require( '@playwright/test' );

test( 'dashboard loads and the overview renders', async ( { page } ) => {
	await page.goto( '/wp-admin/admin.php?page=ai-parseable' );
	await expect( page.locator( '.aip-status' ) ).toBeVisible();
	// A fresh install shows the empty state; a site with data shows the chart. Either is a successful paint.
	await expect( page.locator( 'svg.aip-chart, .aip-empty' ).first() ).toBeVisible();
	const errors = [];
	page.on( 'pageerror', ( e ) => errors.push( e.message ) );
	await page.waitForTimeout( 500 );
	expect( errors ).toEqual( [] );
} );

// Set a crawler's rule through the UI and wait for the save to be confirmed.
async function setRule( page, bot, rule ) {
	const row = page.locator( `tr:has-text("${ bot }")` ).first();
	await row.locator( `.aip-segmented button:has-text("${ rule }")` ).click();
	await expect( page.locator( '.aip-savebar.is-dirty' ) ).toBeVisible();
	await page.click( '.aip-savebar button:has-text("Save rules")' );
	await expect( page.locator( '.aip-savebar' ) ).toContainText( 'Rules saved' );
}

test( 'a crawler rule persists and reaches robots.txt', async ( { page } ) => {
	await page.goto( '/wp-admin/admin.php?page=ai-parseable#crawlers' );
	await page.waitForSelector( '.aip-rules' );
	const active = page.locator( 'tr:has-text("GPTBot")' ).first().locator( '.aip-segmented button.is-active' );

	// The site may already block GPTBot; start from Allow so the change below is a real one.
	if ( ( await active.textContent() ) === 'Block' ) {
		await setRule( page, 'GPTBot', 'Allow' );
	}
	await setRule( page, 'GPTBot', 'Block' );

	await page.reload();
	await page.waitForSelector( '.aip-rules' );
	await expect( active ).toHaveText( 'Block' );

	const robots = await page.request.get( '/robots.txt' );
	expect( await robots.text() ).toContain( 'User-agent: GPTBot\nDisallow: /' );
} );

test( 'llms.txt preview follows the summary and is served once saved', async ( { page } ) => {
	// A unique summary so the editor is dirty even when a previous run saved this test's text.
	const summary = `Example Co sells widgets to 40 countries from its factory in Leeds (${ Date.now() }).`;
	await page.goto( '/wp-admin/admin.php?page=ai-parseable#llms' );
	await page.waitForSelector( 'textarea' );
	await page.fill( 'textarea', summary );
	await expect( page.locator( '.aip-pre' ) ).toContainText( summary );
	await page.click( '.aip-savebar button:has-text("Save")' );
	await expect( page.locator( '.aip-savebar' ) ).toContainText( 'Saved' );
	const llms = await page.request.get( '/llms.txt' );
	expect( llms.status() ).toBe( 200 );
	expect( await llms.text() ).toContain( `> ${ summary }` );
} );
