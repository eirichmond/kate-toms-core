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

/**
 * CSS class applied to the chevron button injected on parent items.
 */
const CHEVRON_CLASS = 'ktc-drilldown__chevron';

/**
 * CSS class applied to the `<img>` inside the chevron button.
 */
const CHEVRON_ICON_CLASS = 'ktc-drilldown__chevron-icon';

/**
 * CSS class applied to the header bar inside each drilled child panel.
 */
const PANEL_HEADER_CLASS = 'ktc-drilldown__panel-header';

/**
 * CSS class applied to the back button inside a child panel header.
 */
const BACK_BUTTON_CLASS = 'ktc-drilldown__back';

/**
 * Per-`<li>` cache of lazily-built child panels. Using WeakMap means
 * entries are automatically garbage-collected when the `<li>` is removed
 * from the DOM (e.g. by unwrapOverlays during a breakpoint reset).
 *
 * @type {WeakMap<HTMLElement, HTMLElement>}
 */
const childPanelCache = new WeakMap();

/**
 * DOM id of the `<script type="application/json">` tag WordPress prints
 * for this module's `script_module_data_{id}` filter return value.
 */
const MODULE_DATA_ID =
	'wp-script-module-data-kate-toms-core/mobile-nav-drilldown';

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
 * Read the runtime data WordPress injected for this script module.
 *
 * Returns an object — currently carries only `arrowSrc`, the absolute
 * URL of the chevron PNG. Cached after first read since the `<script>`
 * tag is static.
 *
 * @return {{ arrowSrc?: string }} The parsed module data, or `{}` on error.
 */
let cachedModuleData = null;
function getModuleData() {
	if ( cachedModuleData !== null ) {
		return cachedModuleData;
	}
	const el = document.getElementById( MODULE_DATA_ID );
	if ( ! el ) {
		cachedModuleData = {};
		return cachedModuleData;
	}
	try {
		cachedModuleData = JSON.parse( el.textContent ) || {};
	} catch ( err ) {
		cachedModuleData = {};
	}
	return cachedModuleData;
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
 * Extract a human-readable label for a parent `<li>`.
 *
 * Walks the direct children and returns the text content of the first
 * anchor or the first text-bearing element. Falls back to the `<li>`'s
 * own `textContent` (trimmed) if nothing better can be found.
 *
 * @param {HTMLElement} li The parent list item.
 * @return {string} Trimmed label text, or an empty string.
 */
function getParentLabel( li ) {
	const link = li.querySelector( ':scope > a' );
	if ( link && link.textContent ) {
		return link.textContent.trim();
	}
	return ( li.textContent || '' ).trim();
}

/**
 * Build a drilldown chevron `<button>` for a parent item.
 *
 * The button contains an `<img>` using the `arrowSrc` provided by the
 * PHP side via script module data. The `<img>` itself is marked
 * decorative (`alt=""`); the button carries the accessible label.
 *
 * @param {string} parentLabel Trimmed label of the parent item.
 * @param {string} arrowSrc    Absolute URL of the chevron image.
 * @return {HTMLButtonElement} The chevron button element.
 */
function createChevronButton( parentLabel, arrowSrc ) {
	const btn = document.createElement( 'button' );
	btn.type = 'button';
	btn.className = CHEVRON_CLASS;
	btn.setAttribute( 'aria-expanded', 'false' );
	btn.setAttribute( 'aria-label', `Show submenu for ${ parentLabel }` );

	const img = document.createElement( 'img' );
	img.src = arrowSrc;
	img.alt = '';
	img.className = CHEVRON_ICON_CLASS;
	btn.appendChild( img );

	return btn;
}

/**
 * Build a minimal back-button element for a child panel header.
 *
 * Task 5.3 refines this to use the arrow image rotated 180° and computes
 * the grandparent label. For now the button carries a plain text label
 * so task 4.3's structural check has something to assert against.
 *
 * @param {string} label Visible text after the arrow glyph.
 * @return {HTMLButtonElement} The back button.
 */
function createBackButton( label ) {
	const btn = document.createElement( 'button' );
	btn.type = 'button';
	btn.className = BACK_BUTTON_CLASS;
	btn.textContent = `\u2190 ${ label }`;
	btn.setAttribute( 'aria-label', `Back to ${ label }` );
	return btn;
}

/**
 * Build the header row that sits at the top of every drilled panel.
 *
 * @param {string} backLabel Label shown on the back button.
 * @return {HTMLElement} The header element containing the back button.
 */
function createPanelHeader( backLabel ) {
	const header = document.createElement( 'div' );
	header.className = PANEL_HEADER_CLASS;
	header.appendChild( createBackButton( backLabel ) );
	return header;
}

/**
 * Lazily build the child panel for a given parent `<li>`.
 *
 * On first call: clones the parent's nested `<ul>`, wraps it in a new
 * panel element with a header, appends the panel to the overlay's track,
 * and caches the result on the parent via `childPanelCache`. Subsequent
 * calls return the cached element directly.
 *
 * Cloning (not detaching) is deliberate: `unwrapOverlays()` moves the
 * root `<ul>` back out of the wrapper and destroys the wrapper + panels.
 * If we detached the child `<ul>`s, they would be lost on resize-above
 * cleanup. Cloning leaves the original tree intact.
 *
 * @param {HTMLElement} parentLi Parent list item whose submenu is being
 *                               drilled into. Must be inside an overlay
 *                               that has already been wrapped.
 * @return {HTMLElement|null} The panel element, or null if the overlay
 *                            structure isn't ready or there's no child
 *                            list to drill into.
 */
function buildChildPanel( parentLi ) {
	const cached = childPanelCache.get( parentLi );
	if ( cached ) {
		return cached;
	}

	const sourceList = Array.from( parentLi.children ).find(
		( child ) => child.tagName === 'UL'
	);
	if ( ! sourceList ) {
		return null;
	}

	const sourcePanel = parentLi.closest( `.${ PANEL_CLASS }` );
	const track = parentLi.closest( `.${ TRACK_CLASS }` );
	if ( ! sourcePanel || ! track ) {
		return null;
	}

	const sourceLevel = Number.parseInt(
		sourcePanel.getAttribute( 'data-level' ) || '0',
		10
	);
	const newLevel = sourceLevel + 1;

	const panel = document.createElement( 'div' );
	panel.className = PANEL_CLASS;
	panel.setAttribute( 'data-level', String( newLevel ) );

	// Record the parent label on the panel so task 5.3 can look it up
	// when rendering the back button of a further-nested child panel.
	const parentLabel = getParentLabel( parentLi );
	panel.dataset.parentLabel = parentLabel;

	// Level-1 panels go back to "Menu"; deeper panels go back to the
	// label of the source panel's own parent (resolved via data set
	// earlier for that source panel). Task 5.3 will refine this.
	const backLabel =
		sourceLevel === 0 ? 'Menu' : sourcePanel.dataset.parentLabel || 'Menu';

	panel.appendChild( createPanelHeader( backLabel ) );
	panel.appendChild( sourceList.cloneNode( true ) );

	track.appendChild( panel );
	childPanelCache.set( parentLi, panel );

	return panel;
}

/**
 * Chevron click handler — for now just materialises the child panel
 * (idempotent via the cache) so task 4.3's DOM check passes. Task 5.1
 * extends this to also run the drillIn slide transition and focus work.
 *
 * @param {HTMLElement} parentLi The parent list item whose chevron was
 *                               clicked.
 * @return {void}
 */
function onChevronClick( parentLi ) {
	buildChildPanel( parentLi );
}

/**
 * Inject a chevron button into every tagged parent `<li>` in the overlay.
 *
 * Idempotent: if a chevron already exists inside the `<li>`, skips it.
 * The chevron is appended as the last direct child of the `<li>` and is
 * wired to `onChevronClick()`.
 *
 * No-op if `arrowSrc` is missing from the module data (e.g. the PHP
 * filter didn't run) — better to render nothing than to produce broken
 * image icons.
 *
 * @param {HTMLElement} overlay The open overlay root element.
 * @return {void}
 */
function injectChevrons( overlay ) {
	const { arrowSrc } = getModuleData();
	if ( ! arrowSrc ) {
		return;
	}

	const parents = overlay.querySelectorAll(
		'li[data-drilldown-parent="true"]'
	);
	parents.forEach( ( li ) => {
		if ( li.querySelector( `:scope > .${ CHEVRON_CLASS }` ) ) {
			return;
		}
		const label = getParentLabel( li );
		const button = createChevronButton( label, arrowSrc );
		button.addEventListener( 'click', ( event ) => {
			event.preventDefault();
			event.stopPropagation();
			onChevronClick( li );
		} );
		li.appendChild( button );
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
	injectChevrons( overlay );
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

	const chevrons = document.querySelectorAll( `.${ CHEVRON_CLASS }` );
	chevrons.forEach( ( btn ) => btn.remove() );
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
