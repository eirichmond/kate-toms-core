/**
 * Mobile Nav Drilldown — view module.
 *
 * Extends the core/navigation Interactivity store to turn the mobile
 * overlay into an iOS-style drilldown below 1100px. This file is loaded
 * as a script module and enqueued by Kate_Toms_Core_Mobile_Nav only on
 * pages that render a core/navigation block.
 */
/* global MutationObserver, Element */
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
 * CSS class applied to the back button's `<img>`. Task 7.3 rotates it
 * 180° via CSS so the same `right-arrow.png` asset is reused for both
 * forward (chevron) and back-button directions.
 */
const BACK_ICON_CLASS = 'ktc-drilldown__back-icon';

/**
 * CSS class applied to the synthesised "View [Parent]" list item that
 * keeps the parent link reachable from inside its own drilled submenu.
 */
const VIEW_PARENT_CLASS = 'ktc-drilldown__view-parent';

/**
 * Per-`<li>` cache of lazily-built child panels. Using WeakMap means
 * entries are automatically garbage-collected when the `<li>` is removed
 * from the DOM (e.g. by unwrapOverlays during a breakpoint reset).
 *
 * @type {WeakMap<HTMLElement, HTMLElement>}
 */
const childPanelCache = new WeakMap();

/**
 * Per-panel record of the chevron button that triggered its drill-in.
 * Used by `drillBack` (task 5.2) to restore focus to the originating
 * chevron when the user pops back up the stack.
 *
 * @type {WeakMap<HTMLElement, HTMLElement>}
 */
const panelTriggers = new WeakMap();

/**
 * Monotonic counter for generating stable, unique child panel ids.
 * Ids are pushed onto `state.drilldownPath` so the stack can identify
 * which panel is active at each level.
 */
let panelIdCounter = 0;

/**
 * DOM id of the `<script type="application/json">` tag WordPress prints
 * for this module's `script_module_data_{id}` filter return value.
 */
const MODULE_DATA_ID =
	'wp-script-module-data-kate-toms-core/mobile-nav-drilldown';

/**
 * CSS class applied to the visually-hidden `aria-live="polite"` element
 * that announces drilldown level changes to assistive tech.
 */
const LIVE_REGION_CLASS = 'ktc-drilldown__live-region';

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

	const liveRegion = document.createElement( 'div' );
	liveRegion.className = LIVE_REGION_CLASS;
	liveRegion.setAttribute( 'aria-live', 'polite' );
	liveRegion.setAttribute( 'aria-atomic', 'true' );
	// Inline visually-hidden styles so the region never shows even if
	// task 7's CSS hasn't loaded. Task 7 may refine via the class.
	liveRegion.style.position = 'absolute';
	liveRegion.style.width = '1px';
	liveRegion.style.height = '1px';
	liveRegion.style.overflow = 'hidden';
	liveRegion.style.clip = 'rect(0 0 0 0)';
	liveRegion.style.whiteSpace = 'nowrap';
	liveRegion.style.border = '0';
	liveRegion.style.padding = '0';
	liveRegion.style.margin = '-1px';
	wrapper.appendChild( liveRegion );

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

	// Keyboard shortcuts are scoped to the wrapper — removing the
	// wrapper (via unwrapOverlays) removes the listener with it.
	wrapper.addEventListener( 'keydown', ( event ) => {
		onWrapperKeydown( event, wrapper );
	} );

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
 * Tag every direct-child `<li>` of the given `<ul>` that contains a
 * nested `<ul>` with `data-drilldown-parent="true"`.
 *
 * Deliberately scoped to ONE level (`:scope > li`): deeper levels are
 * processed only when their own drilled panel is built, so the clone
 * we copy into a new panel never carries chevrons for deeper levels
 * (whose click listeners would be lost by `cloneNode`).
 *
 * @param {HTMLElement} list The `<ul>` whose immediate children to scan.
 * @return {void}
 */
function tagParentItemsInList( list ) {
	const items = list.querySelectorAll( ':scope > li' );
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
 * Extract the linkable `href` for a parent `<li>`, if it has one.
 *
 * Returns `null` for pure-container parents — items whose direct `<a>`
 * either doesn't exist or points at `#` / `javascript:` / an empty
 * string. Those cases must not produce a synthesised "View [Parent]"
 * entry because the link would be dead.
 *
 * @param {HTMLElement} li The parent list item.
 * @return {string|null} The parent URL, or `null` if not linkable.
 */
function getParentHref( li ) {
	const link = li.querySelector( ':scope > a' );
	if ( ! link ) {
		return null;
	}
	const href = link.getAttribute( 'href' );
	if ( ! href ) {
		return null;
	}
	const trimmed = href.trim();
	if ( trimmed === '' || trimmed === '#' ) {
		return null;
	}
	if ( trimmed.toLowerCase().startsWith( 'javascript:' ) ) {
		return null;
	}
	return trimmed;
}

/**
 * Build a synthesised "View [Parent Label]" list item for the top of a
 * cloned child submenu. Returns `null` if the parent has no usable href
 * so callers can simply skip the prepend.
 *
 * @param {HTMLElement} parentLi The parent list item being drilled into.
 * @return {HTMLLIElement|null} The synthesised list item, or null.
 */
function createViewParentItem( parentLi ) {
	const href = getParentHref( parentLi );
	if ( ! href ) {
		return null;
	}

	const label = getParentLabel( parentLi );
	const li = document.createElement( 'li' );
	li.className = VIEW_PARENT_CLASS;

	const link = document.createElement( 'a' );
	link.href = href;
	link.textContent = `View ${ label }`;
	li.appendChild( link );

	return li;
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
 * Build the back-button element for a child panel header.
 *
 * Reuses the same `right-arrow.png` asset injected in task 4.2 and
 * relies on the CSS rotation applied by task 7.3 to flip it to point
 * left. If `arrowSrc` isn't available from module data (the PHP filter
 * didn't run), the button falls back to a text arrow glyph so the
 * control is still usable.
 *
 * The click handler resolves its own track via `closest()` so the
 * button is self-contained — callers don't need to pass a reference.
 *
 * @param {string} label Visible text shown after the arrow.
 * @return {HTMLButtonElement} The back button.
 */
function createBackButton( label ) {
	const btn = document.createElement( 'button' );
	btn.type = 'button';
	btn.className = BACK_BUTTON_CLASS;
	btn.setAttribute( 'aria-label', `Back to ${ label }` );

	const { arrowSrc } = getModuleData();
	if ( arrowSrc ) {
		const img = document.createElement( 'img' );
		img.src = arrowSrc;
		img.alt = '';
		img.className = BACK_ICON_CLASS;
		btn.appendChild( img );
		btn.appendChild( document.createTextNode( ` ${ label }` ) );
	} else {
		btn.textContent = `\u2190 ${ label }`;
	}

	btn.addEventListener( 'click', ( event ) => {
		event.preventDefault();
		event.stopPropagation();
		const track = btn.closest( `.${ TRACK_CLASS }` );
		if ( track ) {
			drillBack( track );
		}
	} );
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

	panelIdCounter += 1;
	const panel = document.createElement( 'div' );
	panel.id = `ktc-drilldown-panel-${ panelIdCounter }`;
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

	const clonedList = sourceList.cloneNode( true );
	const viewParentItem = createViewParentItem( parentLi );
	if ( viewParentItem ) {
		clonedList.insertBefore( viewParentItem, clonedList.firstChild );
	}
	panel.appendChild( clonedList );

	track.appendChild( panel );
	childPanelCache.set( parentLi, panel );

	// Process this level so any parents inside the cloned list get
	// their own chevrons. Deeper levels are only built on subsequent
	// drills, so the clone we just appended never contains orphaned
	// (listener-less) chevrons from levels deeper than this one.
	processListLevel( clonedList );

	return panel;
}

/**
 * Apply the current drill level's horizontal offset to the wrapper.
 *
 * Each drilled level shifts the track left by one viewport-width of the
 * wrapper (-N * 100%). Task 7.1 adds the CSS transition; task 7.2 moves
 * this assignment onto a CSS custom property instead of inline transform.
 *
 * @param {HTMLElement} wrapper The `.ktc-drilldown` wrapper element.
 * @return {void}
 */
function applyWrapperTransform( wrapper ) {
	const level = state.drilldownPath.length;
	wrapper.style.transform = `translateX(-${ level * 100 }%)`;
}

/**
 * Find the panel that is currently "active" (visible and interactive).
 *
 * Level 0: the root panel. Level ≥ 1: the panel whose id matches the
 * last entry on `state.drilldownPath`.
 *
 * @param {HTMLElement} track The `.ktc-drilldown__track` element.
 * @return {HTMLElement|null} The active panel, or null if not found.
 */
function getActivePanel( track ) {
	if ( state.drilldownPath.length === 0 ) {
		return track.querySelector( `.${ PANEL_CLASS }[data-level="0"]` );
	}
	const activeId = state.drilldownPath[ state.drilldownPath.length - 1 ];
	return track.querySelector( `#${ activeId }` );
}

/**
 * Sync every chevron's `aria-expanded` attribute to the current drill
 * path — authoritative single source of truth.
 *
 * For each chevron in the track: find its parent `<li>`, look up its
 * lazily-built panel in `childPanelCache`, and set `aria-expanded` to
 * `true` iff that panel's id is currently on `state.drilldownPath`.
 * Chevrons whose panels haven't been built yet (never drilled into)
 * stay at `false`.
 *
 * Computing this from the path on every transition is more robust than
 * flipping individual chevrons, because it stays correct for any
 * future interaction that pops or pushes more than one level at a time.
 *
 * @param {HTMLElement} track The `.ktc-drilldown__track` element.
 * @return {void}
 */
function syncChevronExpanded( track ) {
	const chevrons = track.querySelectorAll( `.${ CHEVRON_CLASS }` );
	chevrons.forEach( ( btn ) => {
		const li = btn.parentElement;
		const panel = li ? childPanelCache.get( li ) : null;
		const expanded = !! (
			panel && state.drilldownPath.includes( panel.id )
		);
		btn.setAttribute( 'aria-expanded', expanded ? 'true' : 'false' );
	} );
}

/**
 * Mark every panel in the track either active (focusable, visible to
 * assistive tech) or inactive (`inert` + `aria-hidden="true"`).
 *
 * Task 6.3 already-correct behaviour: all non-active panels get inert
 * regardless of whether they're ancestors or siblings of the active
 * panel, so focus can never escape to off-screen items.
 *
 * @param {HTMLElement} track The `.ktc-drilldown__track` element.
 * @return {void}
 */
function updatePanelInertness( track ) {
	const active = getActivePanel( track );
	const panels = track.querySelectorAll( `.${ PANEL_CLASS }` );
	panels.forEach( ( panel ) => {
		if ( panel === active ) {
			panel.removeAttribute( 'inert' );
			panel.removeAttribute( 'aria-hidden' );
		} else {
			panel.setAttribute( 'inert', '' );
			panel.setAttribute( 'aria-hidden', 'true' );
		}
	} );
}

/**
 * Update the wrapper's `aria-live` region with the current level label.
 *
 * Level 0 announces "Top menu"; deeper levels announce "In submenu:
 * {parentLabel}" based on the currently-active panel's stored parent
 * label. Silent if the wrapper has no live region (defensive).
 *
 * @param {HTMLElement} wrapper The `.ktc-drilldown` wrapper element.
 * @return {void}
 */
function announceLevelChange( wrapper ) {
	const liveRegion = wrapper.querySelector( `.${ LIVE_REGION_CLASS }` );
	if ( ! liveRegion ) {
		return;
	}

	if ( state.drilldownPath.length === 0 ) {
		liveRegion.textContent = 'Top menu';
		return;
	}

	const track = wrapper.querySelector( `.${ TRACK_CLASS }` );
	if ( ! track ) {
		return;
	}
	const active = getActivePanel( track );
	const label = ( active && active.dataset.parentLabel ) || 'submenu';
	liveRegion.textContent = `In submenu: ${ label }`;
}

/**
 * Move focus to the best starting element inside a panel.
 *
 * Preference order:
 *   1. The synthesised "View [Parent]" link at the top of the list.
 *   2. The first anchor or button inside the panel's `<ul>`.
 *   3. The back button in the panel header.
 *
 * @param {HTMLElement} panel The panel to focus into.
 * @return {void}
 */
function focusFirstIn( panel ) {
	const viewParent = panel.querySelector( `.${ VIEW_PARENT_CLASS } a` );
	if ( viewParent ) {
		viewParent.focus();
		return;
	}
	const firstItemLink = panel.querySelector( 'ul a, ul button' );
	if ( firstItemLink ) {
		firstItemLink.focus();
		return;
	}
	const back = panel.querySelector( `.${ BACK_BUTTON_CLASS }` );
	if ( back ) {
		back.focus();
	}
}

/**
 * Drill back one level: pop the path stack, reverse the slide, update
 * inertness, and restore focus to the chevron that opened the panel
 * we just left.
 *
 * No-op if we're already at the root (`state.drilldownPath` is empty);
 * callers relying on Escape fall-through at root should check the path
 * length themselves and not delegate to this function (see task 5.4).
 *
 * @param {HTMLElement} track The `.ktc-drilldown__track` element.
 * @return {void}
 */
function drillBack( track ) {
	if ( state.drilldownPath.length === 0 ) {
		return;
	}

	// Capture the panel and trigger we're leaving BEFORE mutating the
	// path, because `getActivePanel` reads from `state.drilldownPath`.
	const leavingPanel = getActivePanel( track );
	const trigger = leavingPanel ? panelTriggers.get( leavingPanel ) : null;

	state.drilldownPath.pop();

	const wrapper = track.closest( `.${ WRAPPER_CLASS }` );
	if ( wrapper ) {
		applyWrapperTransform( wrapper );
		announceLevelChange( wrapper );
	}
	updatePanelInertness( track );
	syncChevronExpanded( track );

	if ( trigger ) {
		trigger.focus();
	}
}

/**
 * Drill in to a child panel: push its id on the path stack, slide the
 * wrapper, update inertness, and move focus.
 *
 * Records the triggering chevron on the panel via `panelTriggers` so
 * the matching drill-back (task 5.2) can restore focus correctly.
 *
 * @param {HTMLElement} panel         The panel being drilled into.
 * @param {HTMLElement} triggerButton The chevron that initiated the drill.
 * @return {void}
 */
function drillIn( panel, triggerButton ) {
	const track = panel.closest( `.${ TRACK_CLASS }` );
	const wrapper = panel.closest( `.${ WRAPPER_CLASS }` );
	if ( ! track || ! wrapper ) {
		return;
	}

	panelTriggers.set( panel, triggerButton );
	state.drilldownPath.push( panel.id );

	applyWrapperTransform( wrapper );
	updatePanelInertness( track );
	syncChevronExpanded( track );
	announceLevelChange( wrapper );
	focusFirstIn( panel );
}

/**
 * Chevron click handler — materialises the target child panel (if not
 * already cached) and drills into it.
 *
 * @param {HTMLElement} parentLi      The parent list item whose chevron
 *                                    was clicked.
 * @param {HTMLElement} triggerButton The chevron button itself, used for
 *                                    focus restoration on drill-back.
 * @return {void}
 */
function onChevronClick( parentLi, triggerButton ) {
	const panel = buildChildPanel( parentLi );
	if ( ! panel ) {
		return;
	}
	drillIn( panel, triggerButton );
}

/**
 * Ignore keyboard shortcuts when focus is inside a form control. Prevents
 * us from hijacking arrow keys during typing — search boxes inside a
 * navigation submenu (unlikely but possible) must still behave normally.
 *
 * @param {EventTarget|null} target The focused element.
 * @return {boolean} True if keyboard shortcuts should be ignored.
 */
function isTypingTarget( target ) {
	if ( ! target || ! ( target instanceof Element ) ) {
		return false;
	}
	if ( target.isContentEditable ) {
		return true;
	}
	const tag = target.tagName;
	return tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT';
}

/**
 * Handle keydown events inside a drilldown wrapper.
 *
 * Shortcuts:
 *   - Escape inside a drilled panel → drillBack. At level 0 the event
 *     is intentionally NOT consumed, so core/navigation's own
 *     close-overlay handler still runs.
 *   - ArrowRight on a focused element inside a parent `<li>` that has
 *     a chevron → drillIn that parent.
 *   - ArrowLeft inside any drilled panel (not the root) → drillBack.
 *
 * @param {KeyboardEvent} event   The keydown event.
 * @param {HTMLElement}   wrapper The `.ktc-drilldown` wrapper element
 *                                the listener is attached to.
 * @return {void}
 */
function onWrapperKeydown( event, wrapper ) {
	if ( isTypingTarget( event.target ) ) {
		return;
	}

	const track = wrapper.querySelector( `.${ TRACK_CLASS }` );
	if ( ! track ) {
		return;
	}

	if ( event.key === 'Escape' ) {
		if ( state.drilldownPath.length > 0 ) {
			event.preventDefault();
			event.stopPropagation();
			drillBack( track );
		}
		return;
	}

	if ( event.key === 'ArrowRight' ) {
		const li = event.target.closest( 'li[data-drilldown-parent="true"]' );
		if ( ! li || ! wrapper.contains( li ) ) {
			return;
		}
		const chevron = li.querySelector( `:scope > .${ CHEVRON_CLASS }` );
		if ( ! chevron ) {
			return;
		}
		event.preventDefault();
		onChevronClick( li, chevron );
		return;
	}

	if ( event.key === 'ArrowLeft' ) {
		if ( state.drilldownPath.length === 0 ) {
			return;
		}
		const panel = event.target.closest( `.${ PANEL_CLASS }` );
		if ( ! panel || panel.getAttribute( 'data-level' ) === '0' ) {
			return;
		}
		event.preventDefault();
		drillBack( track );
	}
}

/**
 * Inject a chevron button into every tagged direct-child `<li>` of the
 * given `<ul>`. Idempotent: `<li>`s that already have a chevron are
 * skipped.
 *
 * @param {HTMLElement} list     The `<ul>` whose children to scan.
 * @param {string}      arrowSrc Absolute URL of the chevron image.
 * @return {void}
 */
function injectChevronsInList( list, arrowSrc ) {
	const parents = list.querySelectorAll(
		':scope > li[data-drilldown-parent="true"]'
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
			onChevronClick( li, button );
		} );
		li.appendChild( button );
	} );
}

/**
 * Process one level of a `<ul>`: tag parent items and inject chevrons
 * on direct children only. Called once per level — by `onOverlayOpen`
 * for the root list, and by `buildChildPanel` for each newly-cloned
 * list as the user drills deeper. This is how the drilldown supports
 * arbitrary nesting depth without pre-building everything up front.
 *
 * No-op if the `arrowSrc` module data isn't available.
 *
 * @param {HTMLElement} list The `<ul>` to process.
 * @return {void}
 */
function processListLevel( list ) {
	const { arrowSrc } = getModuleData();
	if ( ! arrowSrc ) {
		return;
	}
	tagParentItemsInList( list );
	injectChevronsInList( list, arrowSrc );
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
	const rootList = overlay.querySelector(
		`.${ PANEL_CLASS }[data-level="0"] ${ ROOT_LIST_SELECTOR }`
	);
	if ( rootList ) {
		processListLevel( rootList );
	}
}

/**
 * Reset drilldown state when the overlay closes.
 *
 * Differs from `resetDrilldownState()` (used on breakpoint reset): this
 * keeps the wrapper, built panels, and chevrons in place so reopening
 * the overlay is instant. Only the drill path, transform, inertness,
 * and `aria-expanded` states are rewound.
 *
 * Idempotent — safe to call on overlays that never had a wrapper
 * injected (e.g. desktop-only navs, or the initial pre-open state).
 *
 * @param {HTMLElement} overlay The overlay that just closed.
 * @return {void}
 */
function onOverlayClose( overlay ) {
	const wrapper = overlay.querySelector( `.${ WRAPPER_CLASS }` );
	if ( ! wrapper ) {
		return;
	}
	const track = wrapper.querySelector( `.${ TRACK_CLASS }` );
	if ( ! track ) {
		return;
	}

	state.drilldownPath.length = 0;
	wrapper.style.transform = '';
	updatePanelInertness( track );
	syncChevronExpanded( track );

	// Clear the live region so the next open doesn't start with a
	// stale "In submenu: X" announcement.
	const liveRegion = wrapper.querySelector( `.${ LIVE_REGION_CLASS }` );
	if ( liveRegion ) {
		liveRegion.textContent = '';
	}
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
		} else {
			onOverlayClose( overlay );
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
