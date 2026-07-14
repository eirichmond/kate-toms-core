/**
 * Party house landing pages — e2e regression tests.
 *
 * Guards BugHerd #432 (/party-houses/london/) and #433 (/party-houses/wales/),
 * where every section on the page rendered zero houses because the location
 * tax_query ANDed a section's broad region together with all of the granular
 * locations the migration injects — asking for one house to be in several
 * counties at once.
 *
 * These run against the live Valet site at https://kateandtomsblocks.test and
 * therefore depend on its content, in keeping with the rest of this suite.
 *
 * Run with: `npm run test:e2e:local`
 */
const { test, expect } = require( '@playwright/test' );

/**
 * The landing pages under test, each with a house that is known to qualify
 * and so must appear once the region/granular split is applied correctly.
 */
const LANDING_PAGES = [
	{
		bugherd: 432,
		path: '/party-houses/london/',
		// In Town + London, with the Party House feature.
		expectedHouse: 'palmgate-retreat',
	},
	{
		bugherd: 433,
		path: '/party-houses/wales/',
		// By the Coast + In the Country + Wales, with the Party House feature.
		expectedHouse: 'scholars-hall',
	},
];

for ( const { bugherd, path, expectedHouse } of LANDING_PAGES ) {
	test.describe( `${ path } (BugHerd #${ bugherd })`, () => {
		test.beforeEach( async ( { page } ) => {
			await page.goto( path );
		} );

		test( 'renders house cards', async ( { page } ) => {
			// Scoped to the card markup: the nav also links to /houses/, so an
			// unscoped locator would pass even with every section empty.
			const cards = page.locator( '.house-card' );

			await expect( cards.first() ).toBeVisible();
			expect( await cards.count() ).toBeGreaterThan( 0 );
		} );

		test( 'does not show the empty-results notice', async ( { page } ) => {
			await expect(
				page.getByText( /no houses/i ).first()
			).toBeHidden();
		} );

		test( `includes ${ expectedHouse }, which qualifies for this page`, async ( {
			page,
		} ) => {
			await expect(
				page
					.locator( `.house-card a[href*="/houses/${ expectedHouse }/"]` )
					.first()
			).toBeAttached();
		} );
	} );
}
