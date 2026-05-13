/**
 * Mobile Nav Drilldown — e2e regression tests.
 *
 * All tests run against the live Valet site at https://kateandtomsblocks.test
 * at a 375px mobile viewport (configured in playwright.config.js).
 *
 * Implementation notes (post parent-as-trigger refactor):
 *   - The chevron is now a decorative <span aria-hidden="true">, not a <button>.
 *   - Drill-in is triggered by clicking the parent <a> link directly.
 *   - aria-expanded is tracked on the parent <a>, not the chevron.
 *   - Focus restoration on drill-back lands on the parent <a>.
 */
const { test, expect } = require( '@playwright/test' );

/**
 * Open the mobile nav overlay and return a locator for it.
 * Shared helper used by every test.
 */
async function openOverlay( page ) {
	await page.getByRole( 'button', { name: 'Open menu' } ).first().click();
	const overlay = page.locator(
		'.wp-block-navigation__responsive-container.is-menu-open'
	);
	await expect( overlay ).toBeVisible();
	return overlay;
}

/**
 * Return a locator for the trigger <a> of the first parent item whose
 * text matches `label` within `scope`.
 */
function parentLink( scope, label ) {
	return scope
		.locator( 'li[data-drilldown-parent="true"]', { hasText: label } )
		.first()
		.locator( ':scope > a' )
		.first();
}


test.describe( 'mobile nav drilldown', () => {
	test.beforeEach( async ( { page } ) => {
		// Instant transitions eliminate timing flakiness and verify that the
		// prefers-reduced-motion override is wired correctly.
		await page.emulateMedia( { reducedMotion: 'reduce' } );
		await page.goto( '/' );
	} );

	// ─── Basic single-level drill ───────────────────────────────────────────

	test( 'chevron decoration injected; drilling via parent link; drill back restores focus', async ( {
		page,
	} ) => {
		const overlay = await openOverlay( page );

		// Decorative chevron <span> (not interactive) should be present.
		const chevronSpan = overlay
			.locator( 'li[data-drilldown-parent="true"]', { hasText: 'House Type' } )
			.first()
			.locator( 'span.ktc-drilldown__chevron' );
		await expect( chevronSpan ).toBeVisible();
		await expect( chevronSpan ).toHaveAttribute( 'aria-hidden', 'true' );

		// Drill in via the parent link, not the chevron.
		const houseTypeLink = parentLink( overlay, 'House Type' );
		await expect( houseTypeLink ).toHaveAttribute( 'aria-expanded', 'false' );
		await houseTypeLink.click();

		// Level-1 panel must exist and be non-inert.
		const levelOnePanel = overlay.locator( '.ktc-drilldown__panel[data-level="1"]' );
		await expect( levelOnePanel ).toHaveCount( 1 );
		await expect( levelOnePanel ).not.toHaveAttribute( 'inert' );

		// aria-expanded on the parent link should now be true.
		await expect( houseTypeLink ).toHaveAttribute( 'aria-expanded', 'true' );

		// Focus should be inside the new panel.
		const focusedIsInLevelOne = await page.evaluate( () => {
			const active = document.activeElement;
			return !! ( active && active.closest( '.ktc-drilldown__panel[data-level="1"]' ) );
		} );
		expect( focusedIsInLevelOne ).toBe( true );

		// Active panel should be visually in the viewport (transform is correct).
		await expect(
			levelOnePanel.locator( '.ktc-drilldown__back' )
		).toBeInViewport();

		// Drill back via the panel's back button.
		await levelOnePanel.locator( '.ktc-drilldown__back' ).click();

		// aria-expanded resets.
		await expect( houseTypeLink ).toHaveAttribute( 'aria-expanded', 'false' );

		// Level-1 panel is now inert.
		await expect( levelOnePanel ).toHaveAttribute( 'inert', '' );

		// Focus should return to the parent link.
		const focusReturned = await page.evaluate( () => {
			const active = document.activeElement;
			if ( ! active || active.tagName !== 'A' ) {
				return false;
			}
			const li = active.closest( 'li[data-drilldown-parent="true"]' );
			return li !== null && li.textContent.includes( 'House Type' );
		} );
		expect( focusReturned ).toBe( true );
	} );

	// ─── Three-level drill ──────────────────────────────────────────────────

	test( 'three-level drilldown: Occasion → Birthday → back out', async ( {
		page,
	} ) => {
		const overlay = await openOverlay( page );

		// Drill 1: Occasion (level 0 → 1).
		const occasionLink = parentLink( overlay, 'Occasion' );
		await occasionLink.click();

		const levelOnePanel = overlay.locator( '.ktc-drilldown__panel[data-level="1"]' );
		await expect( levelOnePanel ).not.toHaveAttribute( 'inert' );
		await expect( occasionLink ).toHaveAttribute( 'aria-expanded', 'true' );

		// Drill 2: Birthday (level 1 → 2).
		const birthdayLink = levelOnePanel
			.locator( 'li[data-drilldown-parent="true"]', { hasText: /^Birthday/ } )
			.first()
			.locator( ':scope > a' )
			.first();
		await birthdayLink.click();

		const levelTwoPanel = overlay.locator( '.ktc-drilldown__panel[data-level="2"]' );
		await expect( levelTwoPanel ).not.toHaveAttribute( 'inert' );
		await expect( birthdayLink ).toHaveAttribute( 'aria-expanded', 'true' );

		// Level-2 panel must be visually in viewport.
		await expect(
			levelTwoPanel.locator( '.ktc-drilldown__back' )
		).toBeInViewport();

		// It should contain one of Birthday's child items.
		await expect(
			levelTwoPanel.locator( 'li', { hasText: /\d+th Birthday/ } ).first()
		).toBeVisible();

		// Back to level 1.
		await levelTwoPanel.locator( '.ktc-drilldown__back' ).click();
		await expect( birthdayLink ).toHaveAttribute( 'aria-expanded', 'false' );
		await expect( levelTwoPanel ).toHaveAttribute( 'inert', '' );
		await expect( occasionLink ).toHaveAttribute( 'aria-expanded', 'true' );

		// Level-1 back button must be back in viewport.
		await expect(
			levelOnePanel.locator( '.ktc-drilldown__back' )
		).toBeInViewport();

		// Back to root.
		await levelOnePanel.locator( '.ktc-drilldown__back' ).click();
		await expect( occasionLink ).toHaveAttribute( 'aria-expanded', 'false' );
		await expect( levelOnePanel ).toHaveAttribute( 'inert', '' );
	} );

	// ─── Multi-branch navigation (regression: wrong transform offset) ───────

	test( 'drilling a second root branch after exploring the first shows the correct panel', async ( {
		page,
	} ) => {
		const overlay = await openOverlay( page );

		// Branch 1: drill into House Type.
		const houseTypeLink = parentLink( overlay, 'House Type' );
		await houseTypeLink.click();

		const houseTypePanel = overlay.locator(
			'.ktc-drilldown__panel:not([data-level="0"]):not([inert])'
		);
		// Panel must be in viewport — not obscured by a wrong-offset transform.
		await expect(
			houseTypePanel.locator( '.ktc-drilldown__back' ).first()
		).toBeInViewport();

		// Drill back to root.
		await houseTypePanel.locator( '.ktc-drilldown__back' ).first().click();
		await expect( houseTypeLink ).toHaveAttribute( 'aria-expanded', 'false' );

		// Branch 2: drill into Occasion (a different root parent).
		const occasionLink = parentLink( overlay, 'Occasion' );
		await occasionLink.click();

		await expect( occasionLink ).toHaveAttribute( 'aria-expanded', 'true' );
		await expect( houseTypeLink ).toHaveAttribute( 'aria-expanded', 'false' );

		// The active panel for Occasion must be in viewport.
		// If applyWrapperTransform used path-depth instead of actual panel index,
		// it would slide to the House Type panel (index 1) instead of the
		// Occasion panel (index 2+), and the back button below would be off-screen.
		const occasionPanel = overlay.locator(
			'.ktc-drilldown__panel:not([data-level="0"]):not([inert])'
		);
		await expect(
			occasionPanel.locator( '.ktc-drilldown__back' ).first()
		).toBeInViewport();

		// Items inside the active panel must be interactive (not inert).
		await expect( occasionPanel.locator( '.ktc-drilldown__back' ).first() ).toBeEnabled();
	} );

	// ─── Same-path repeat navigation (regression: skip levels) ──────────────

	test( 'repeating the same drill path does not skip intermediate levels', async ( {
		page,
	} ) => {
		const overlay = await openOverlay( page );

		const occasionLink = parentLink( overlay, 'Occasion' );
		const levelOnePanel = overlay.locator( '.ktc-drilldown__panel[data-level="1"]' );

		const birthdayParentItem = levelOnePanel
			.locator( 'li[data-drilldown-parent="true"]', { hasText: /^Birthday/ } )
			.first();
		const birthdayLink = birthdayParentItem.locator( ':scope > a' ).first();

		const levelTwoPanel = overlay.locator( '.ktc-drilldown__panel[data-level="2"]' );
		const rootPanel = overlay.locator( '.ktc-drilldown__panel[data-level="0"]' );

		// ── First pass: Occasion → Birthday ──
		await occasionLink.click();
		await expect( levelOnePanel ).not.toHaveAttribute( 'inert' );

		await birthdayLink.click();
		await expect( levelTwoPanel ).not.toHaveAttribute( 'inert' );

		// Drill all the way back to root.
		await levelTwoPanel.locator( '.ktc-drilldown__back' ).click();
		await expect( levelTwoPanel ).toHaveAttribute( 'inert', '' );

		await levelOnePanel.locator( '.ktc-drilldown__back' ).click();
		await expect( levelOnePanel ).toHaveAttribute( 'inert', '' );
		await expect( rootPanel ).not.toHaveAttribute( 'inert' );

		// ── Second pass: repeat Occasion — must land at level 1, not skip to 2 ──
		await occasionLink.click();

		// Level 1 must now be the active panel.
		await expect( levelOnePanel ).not.toHaveAttribute( 'inert' );

		// Level 2 must remain inert — the previous visit must not pre-activate it.
		await expect( levelTwoPanel ).toHaveAttribute( 'inert', '' );

		// Level-1 back button must be in viewport (confirms transform is correct).
		await expect(
			levelOnePanel.locator( '.ktc-drilldown__back' )
		).toBeInViewport();

		// Birthday's aria-expanded must be false at this point (not already drilled).
		await expect( birthdayLink ).toHaveAttribute( 'aria-expanded', 'false' );

		// ── Continue the second pass: Birthday — must land at level 2 ──
		await birthdayLink.click();
		await expect( levelTwoPanel ).not.toHaveAttribute( 'inert' );
		await expect( levelOnePanel ).toHaveAttribute( 'inert', '' );

		await expect(
			levelTwoPanel.locator( '.ktc-drilldown__back' )
		).toBeInViewport();
	} );

	// ─── Breakpoint resize ───────────────────────────────────────────────────

	test( 'resize across 1100px breakpoint cleans up and rebuilds on reopen', async ( {
		page,
	} ) => {
		await page.getByRole( 'button', { name: 'Open menu' } ).first().click();
		const overlay = page.locator(
			'.wp-block-navigation__responsive-container.is-menu-open'
		);
		await expect( overlay ).toBeVisible();

		// Drilldown wrapper and decorative chevron spans should be present.
		await expect( page.locator( '.ktc-drilldown' ) ).toHaveCount( 1 );
		expect(
			await page.locator( '.ktc-drilldown__chevron' ).count()
		).toBeGreaterThan( 0 );
		expect(
			await page.locator( '[data-drilldown-parent="true"]' ).count()
		).toBeGreaterThan( 0 );

		// Resize above breakpoint — resetDrilldownState() should fire.
		await page.setViewportSize( { width: 1280, height: 900 } );
		await page.waitForTimeout( 100 );

		expect( await page.locator( '.ktc-drilldown' ).count() ).toBe( 0 );
		expect( await page.locator( '.ktc-drilldown__chevron' ).count() ).toBe( 0 );
		expect( await page.locator( '[data-drilldown-parent]' ).count() ).toBe( 0 );

		// Resize back — wrapper must not be rebuilt until next overlay open.
		await page.setViewportSize( { width: 375, height: 812 } );
		await page.waitForTimeout( 100 );
		expect( await page.locator( '.ktc-drilldown' ).count() ).toBe( 0 );

		// Reload to get a clean overlay state, then reopen.
		await page.reload();
		await page.getByRole( 'button', { name: 'Open menu' } ).first().click();
		await expect( overlay ).toBeVisible();

		await expect( page.locator( '.ktc-drilldown' ) ).toHaveCount( 1 );
		expect(
			await page.locator( '.ktc-drilldown__chevron' ).count()
		).toBeGreaterThan( 0 );
	} );
} );
