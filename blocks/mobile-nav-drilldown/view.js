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

/**
 * CSS class applied to the drilldown stacking wrapper injected by this
 * module around the overlay's root `<ul>`. Used both as a CSS hook and
 * as an idempotency marker — if the wrapper is already present, the
 * wrapping logic no-ops.
 */
const WRAPPER_CLASS = 'ktc-drilldown';

/**
 * CSS class applied to the flex row inside the wrapper that holds all
 * panel elements side-by-side (root + any drilled children).
 */
const TRACK_CLASS = 'ktc-drilldown__track';

/**
 * CSS class applied to each panel (root and drilled).
 */
const PANEL_CLASS = 'ktc-drilldown__panel';

/**
 * Selector for the top-level `<ul>` inside a core navigation overlay.
 * The core block renders the main menu as `ul.wp-block-navigation__container`.
 */
const ROOT_LIST_SELECTOR = 'ul.wp-block-navigation__container';

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
 * Wrap the overlay's root `<ul>` in the drilldown stacking container.
 *
 * Idempotent: if a `.ktc-drilldown` wrapper already exists inside the
 * overlay, returns the existing track element instead of re-wrapping.
 *
 * Resulting structure:
 *
 *   <div class="ktc-drilldown">
 *     <div class="ktc-drilldown__track">
 *       <div class="ktc-drilldown__panel" data-level="0">
 *         <ul class="wp-block-navigation__container"> ... </ul>
 *       </div>
 *     </div>
 *   </div>
 *
 * @param {HTMLElement} overlay The open overlay root element.
 * @return {HTMLElement|null} The track element, or null if no root
 *                            `<ul>` was found inside the overlay.
 */
function wrapOverlay( overlay ) {
	const existingWrapper = overlay.querySelector( `.${ WRAPPER_CLASS }` );
	if ( existingWrapper ) {
		return existingWrapper.querySelector( `.${ TRACK_CLASS }` );
	}

	const rootList = overlay.querySelector( ROOT_LIST_SELECTOR );
	if ( ! rootList ) {
		return null;
	}

	const wrapper = document.createElement( 'div' );
	wrapper.className = WRAPPER_CLASS;

	const track = document.createElement( 'div' );
	track.className = TRACK_CLASS;
	wrapper.appendChild( track );

	const rootPanel = document.createElement( 'div' );
	rootPanel.className = PANEL_CLASS;
	rootPanel.setAttribute( 'data-level', '0' );
	track.appendChild( rootPanel );

	// Insert the wrapper where the root list currently lives, then move
	// the list into the root panel. Doing it in this order keeps focus
	// and any observers on the list itself intact across the move.
	rootList.parentNode.insertBefore( wrapper, rootList );
	rootPanel.appendChild( rootList );

	return track;
}

/**
 * Undo `wrapOverlay()` — move the root list back out of the wrapper and
 * remove the wrapper from the DOM. Used by `resetDrilldownState()` when
 * the viewport crosses above the breakpoint.
 *
 * @return {void}
 */
function unwrapOverlays() {
	const wrappers = document.querySelectorAll( `.${ WRAPPER_CLASS }` );
	wrappers.forEach( ( wrapper ) => {
		const rootList = wrapper.querySelector(
			`[data-level="0"] ${ ROOT_LIST_SELECTOR }`
		);
		if ( rootList && wrapper.parentNode ) {
			wrapper.parentNode.insertBefore( rootList, wrapper );
		}
		wrapper.remove();
	} );
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
	wrapOverlay( overlay );
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
	unwrapOverlays();
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
