/**
 * Mobile Nav Drilldown — view module.
 *
 * Extends the core/navigation Interactivity store to turn the mobile
 * overlay into an iOS-style drilldown below 1100px. This file is loaded
 * as a script module and enqueued by Kate_Toms_Core_Mobile_Nav only on
 * pages that render a core/navigation block.
 */
/* global MutationObserver */
import { store } from '@wordpress/interactivity';
import './style.css';

// eslint-disable-next-line no-console
console.debug( '[mobile-nav-drilldown] loaded' );

/**
 * Maximum viewport width (px) at which the drilldown is active.
 *
 * Must stay in sync with the `@media (max-width: 1100px)` rules in
 * style.css. Declared once here as the single source of truth.
 */
const BREAKPOINT_PX = 1100;

/**
 * CSS selector for the core/navigation overlay root.
 *
 * Core adds the `is-menu-open` class to this element when the overlay
 * opens, so we watch it with a MutationObserver to trigger drilldown
 * initialisation at the right moment.
 */
const OVERLAY_SELECTOR = '.wp-block-navigation__responsive-container';

/**
 * Class core/navigation adds to the overlay when it is open.
 */
const OVERLAY_OPEN_CLASS = 'is-menu-open';

const { state } = store( 'core/navigation', {
	state: {
		/**
		 * Stack of panel ids representing the current drilldown position.
		 *
		 * Level 0 = root panel (empty array). Each drill-in pushes an id,
		 * each drill-back pops one.
		 *
		 * @type {string[]}
		 */
		drilldownPath: [],

		/**
		 * Derived: are we currently inside a drilled panel?
		 *
		 * @return {boolean} True if at least one level deep.
		 */
		get isDrilldown() {
			return state.drilldownPath.length > 0;
		},
	},
} );

/**
 * Check whether the viewport is currently below the drilldown breakpoint.
 *
 * @return {boolean} True if the drilldown should be active.
 */
function isBelowBreakpoint() {
	return window.matchMedia( `(max-width: ${ BREAKPOINT_PX }px)` ).matches;
}

/**
 * Tag every `<li>` inside the given overlay that directly contains a
 * nested `<ul>` with `data-drilldown-parent="true"`.
 *
 * Scans recursively so nested submenus also get their parents marked.
 * Safe to call multiple times — the attribute is idempotent.
 *
 * @param {HTMLElement} overlay The open overlay root element.
 * @return {void}
 */
function tagParentItems( overlay ) {
	const items = overlay.querySelectorAll( 'li' );
	items.forEach( ( li ) => {
		const hasChildList = Array.from( li.children ).some(
			( child ) => child.tagName === 'UL'
		);
		if ( hasChildList ) {
			li.setAttribute( 'data-drilldown-parent', 'true' );
		}
	} );
}

/**
 * Handle overlay open: tag parent items so later tasks can target them.
 *
 * Called by the MutationObserver when the overlay gains `is-menu-open`.
 * No-op above the breakpoint — the enhancement is mobile-only.
 *
 * @param {HTMLElement} overlay The overlay that just opened.
 * @return {void}
 */
function onOverlayOpen( overlay ) {
	if ( ! isBelowBreakpoint() ) {
		return;
	}
	tagParentItems( overlay );
}

/**
 * Observe an overlay element for `is-menu-open` class toggles.
 *
 * @param {HTMLElement} overlay The overlay to watch.
 * @return {void}
 */
function observeOverlay( overlay ) {
	const observer = new MutationObserver( () => {
		if ( overlay.classList.contains( OVERLAY_OPEN_CLASS ) ) {
			onOverlayOpen( overlay );
		}
	} );
	observer.observe( overlay, {
		attributes: true,
		attributeFilter: [ 'class' ],
	} );
}

/**
 * Remove every `data-drilldown-parent` marker from the document.
 *
 * Called when the viewport crosses above the drilldown breakpoint, so
 * leftover markers don't confuse later tasks (e.g. chevron injection)
 * if the user resizes back below the breakpoint.
 *
 * @return {void}
 */
function stripDrilldownAttributes() {
	const tagged = document.querySelectorAll( '[data-drilldown-parent]' );
	tagged.forEach( ( li ) => {
		li.removeAttribute( 'data-drilldown-parent' );
	} );
}

/**
 * Reset all drilldown state: empty the path stack and strip markers.
 *
 * Mutates `state.drilldownPath` in place (via `length = 0`) so
 * Interactivity's reactive tracking picks up the change. Replacing the
 * array with a new reference can miss reactivity depending on how the
 * store proxy was set up.
 *
 * @return {void}
 */
function resetDrilldownState() {
	state.drilldownPath.length = 0;
	stripDrilldownAttributes();
}

/**
 * Wire a listener that cleans up drilldown state when the viewport
 * crosses above the breakpoint. Below-the-breakpoint transitions
 * intentionally do nothing — the next overlay open will re-tag via
 * the MutationObserver.
 *
 * @return {void}
 */
function watchBreakpoint() {
	const mql = window.matchMedia( `(max-width: ${ BREAKPOINT_PX }px)` );
	const handler = ( event ) => {
		if ( ! event.matches ) {
			resetDrilldownState();
		}
	};
	if ( typeof mql.addEventListener === 'function' ) {
		mql.addEventListener( 'change', handler );
	} else {
		// Safari < 14 fallback.
		mql.addListener( handler );
	}
}

/**
 * Initialise observers for every navigation overlay currently in the DOM.
 *
 * @return {void}
 */
function init() {
	const overlays = document.querySelectorAll( OVERLAY_SELECTOR );
	overlays.forEach( observeOverlay );
	watchBreakpoint();
}

if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', init );
} else {
	init();
}
