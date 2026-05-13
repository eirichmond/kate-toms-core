/**
 * Mobile Nav Drilldown — view module.
 *
 * Extends the core/navigation Interactivity store to turn the mobile
 * overlay into an iOS-style drilldown below 1100px. This file is loaded
 * as a script module and enqueued by Kate_Toms_Core_Mobile_Nav only on
 * pages that render a core/navigation block.
 */
/* global MutationObserver, Element */
import './style.css';

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
 * CSS class applied to the decorative chevron span injected on parent items.
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
 * Per-`<li>` cache of lazily-built child panels.
 *
 * @type {WeakMap<HTMLElement, HTMLElement>}
 */
const childPanelCache = new WeakMap();

/**
 * Monotonic counter for generating stable, unique child panel ids.
 * Ids are pushed onto `state.drilldownPath` so the stack can identify
 * which panel is active at each level.
 */
let panelIdCounter = 0;

/**
 * CSS class added to the wrapper while a drill transition is in progress.
 * The stylesheet sets `pointer-events: none` on the wrapper itself while
 * this class is present, suppressing all interaction inside (trigger
 * links, back buttons, View-Parent links). This blocks the iOS ghost-tap
 * cascade where a click dispatched after `touchend` hit-tests onto the
 * back button of the next panel that has slid into the tap position,
 * triggering an unintended second drill.
 *
 * Also consulted by `drillIn`/`drillBack` as a JS-level re-entry guard
 * for events that bypass hit-testing (synthetic clicks, dispatched
 * events).
 */
const DRILLING_CLASS = 'ktc-is-drilling';

/**
 * Mark the start of a drill transition by adding `.ktc-is-drilling` to
 * the wrapper. The stylesheet sets `pointer-events: none` on the wrapper
 * while this class is present, blocking ghost taps and rapid double-taps
 * during the panel reveal. The class is removed on `animationend` (which
 * bubbles up from the active panel's keyframe animation), with a safety
 * timeout in case the animation event never fires (e.g. when
 * prefers-reduced-motion sets animation: none).
 *
 * @param {HTMLElement} track The `.ktc-drilldown__track` element.
 * @return {void}
 */
function activateDrillGuard( track ) {
	const wrapper = track.closest( `.${ WRAPPER_CLASS }` );
	if ( ! wrapper ) {
		return;
	}

	wrapper.classList.add( DRILLING_CLASS );

	const ANIMATION_MS = 200;

	let cleared = false;
	const clear = () => {
		if ( cleared ) {
			return;
		}
		cleared = true;
		wrapper.classList.remove( DRILLING_CLASS );
		wrapper.removeEventListener( 'animationend', clear );
	};

	wrapper.addEventListener( 'animationend', clear );
	setTimeout( clear, ANIMATION_MS + 100 );
}

/**
 * DOM id of the `<script type="application/json">` tag WordPress prints
 * for this module's `script_module_data_{id}` filter return value.
 */
const MODULE_DATA_ID =
	'wp-script-module-data-kate-toms-core/mobile-nav-drilldown';

/**
 * Local drilldown state.
 *
 * We deliberately do NOT register this on the Interactivity API store.
 * The core/navigation store is registered as a *locked* private store
 * by core's own view module, so calling `store('core/navigation', ...)`
 * from an external module throws "Cannot lock a public store" in debug
 * builds and silently fights for ownership in production. Since no
 * reactive subscription actually reads these fields — everything is
 * driven imperatively from the MutationObserver and event listeners —
 * a plain object is simpler and conflict-free.
 *
 * @type {{ drilldownPath: string[] }}
 */
const state = {
	/**
	 * Stack of panel ids representing the current drilldown position.
	 * Level 0 = root panel (empty array). Drill-in pushes; drill-back pops.
	 */
	drilldownPath: [],
};


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
	// tabindex="-1" makes the wrapper a programmatic focus target. We use
	// it as a guaranteed-safe focus fallback during drill-back when there's
	// no element to focus inside the new active panel — focus must stay
	// inside the modal, otherwise core/navigation's focus-out handler
	// closes the overlay.
	wrapper.setAttribute( 'tabindex', '-1' );

	const track = document.createElement( 'div' );
	track.className = TRACK_CLASS;
	wrapper.appendChild( track );

	const rootPanel = document.createElement( 'div' );
	rootPanel.className = PANEL_CLASS;
	rootPanel.setAttribute( 'data-level', '0' );
	// Root is the active panel on initial render. CSS hides any panel
	// without data-active="true", so without this the menu would open
	// blank until the user drills.
	rootPanel.setAttribute( 'data-active', 'true' );
	track.appendChild( rootPanel );

	// Insert the wrapper where the root list currently lives, then move
	// the list into the root panel. Doing it in this order keeps focus
	// and any observers on the list itself intact across the move.
	rootList.parentNode.insertBefore( wrapper, rootList );
	rootPanel.appendChild( rootList );

	return track;
}

/**
 * Module-scope flag for the focus trap. Set to true while we're in the
 * middle of programmatically refocusing the wrapper, so we don't recurse
 * on the focusout that our own focus() call generates.
 */
let trapping = false;

// Document-level capture-phase focus trap. Set up once at module load.
//
// Why document-capture instead of wrapper-bubble:
//
// Core/navigation listens for focusout on the modal (somewhere in the
// bubble path above the wrapper) and closes the overlay when focus
// leaves the modal. A wrapper-bubble listener fires before core's, but
// any wrapper.focus() call made inside a focusout handler is QUEUED by
// the browser — it runs after the current event finishes bubbling. By
// then core has already closed the menu and the refocus is moot.
//
// Document-capture fires before any other listener for the same event.
// Calling stopImmediatePropagation() there prevents the focusout from
// ever reaching core. The queued wrapper.focus() then lands cleanly
// because nothing else has acted on the event.
//
// Allowed exits: tapping the X close button or the hamburger (open)
// button moves focus outside the wrapper. Their click handlers fire
// independently of focus, so the menu still closes / opens normally
// even though we re-grab focus.
document.addEventListener(
	'focusout',
	( event ) => {
		if ( trapping ) {
			return;
		}
		const wrapper = document.querySelector( `.${ WRAPPER_CLASS }` );
		if ( ! wrapper ) {
			return;
		}
		// Only act on focusout originating inside our wrapper.
		if ( ! event.target || ! wrapper.contains( event.target ) ) {
			return;
		}
		// Only act while the overlay is actually open — otherwise we'd
		// fight legitimate close behaviour (Esc, X click, etc.).
		const overlay = wrapper.closest( OVERLAY_SELECTOR );
		if (
			! overlay ||
			! overlay.classList.contains( OVERLAY_OPEN_CLASS )
		) {
			return;
		}
		// Focus staying inside the wrapper is fine.
		const next = event.relatedTarget;
		if ( next && wrapper.contains( next ) ) {
			return;
		}
		// Focus is leaving. Block core's focus-out close handler before
		// it sees the event, then re-grab focus to the wrapper.
		event.stopImmediatePropagation();
		trapping = true;
		wrapper.focus( { preventScroll: true } );
		trapping = false;
	},
	true
);

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

/* CLIENT-DISABLED — uncomment this function (and the corresponding call
 * site in `buildChildPanel`, plus the View-Parent branch in
 * `focusFirstIn`) to restore the synthesised "View [Parent Label]" link
 * at the top of each drilled submenu. Disabled on client request: they
 * prefer drilling in via the parent label without an explicit "View
 * Parent" entry duplicating it.
 */
// eslint-disable-next-line no-unused-vars
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
 * Build a chevron `<span>` for a parent item.
 *
 * Acts as a secondary drill-down trigger alongside the parent `<a>` so a
 * tap on either the label or the arrow opens the submenu. Hidden from
 * assistive tech (`aria-hidden="true"`) — the parent `<a>` is the named,
 * keyboard-accessible drill trigger; the chevron is a redundant pointer
 * affordance that screen-reader users don't need to encounter twice.
 *
 * @param {string} arrowSrc Absolute URL of the chevron image.
 * @return {HTMLSpanElement} The chevron span element.
 */
function createChevronDecoration( arrowSrc ) {
	const span = document.createElement( 'span' );
	span.className = CHEVRON_CLASS;
	span.setAttribute( 'aria-hidden', 'true' );

	const img = document.createElement( 'img' );
	img.src = arrowSrc;
	img.alt = '';
	img.className = CHEVRON_ICON_CLASS;
	span.appendChild( img );

	return span;
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
 * calls return the cached element directly. Cloning (not detaching) is
 * deliberate so the original navigation tree stays intact for desktop.
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
	// CLIENT-DISABLED — uncomment to restore the "View [Parent]" link
	// prepended to each drilled submenu. See createViewParentItem above.
	// const viewParentItem = createViewParentItem( parentLi );
	// if ( viewParentItem ) {
	// 	clonedList.insertBefore( viewParentItem, clonedList.firstChild );
	// }
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
 * Apply the correct horizontal offset for the currently active panel.
 *
 * Writes the `--ktc-drilldown-offset` CSS custom property, which the
 * stylesheet uses inside `transform: translateX(var(...))`. Driving the
 * offset through a custom property (instead of inline `style.transform`)
 * keeps the animation definition entirely in CSS, so the
 * `prefers-reduced-motion` override in task 7.4 can disable the
 * transition without any JS changes.
 *
 * IMPORTANT: the offset is computed from the panel's *actual DOM index*
 * inside the track, not from `drilldownPath.length`. Panels are appended
 * in build order (first-visit order), so after exploring different branches
 * the depth and position can diverge — depth-based offsets would slide the
 * wrong panel into view and leave the real active panel off-screen and
 * visually inaccessible.
 *
 * @param {HTMLElement} wrapper The `.ktc-drilldown` wrapper element.
 * @return {void}
 */
/**
 * Tracks the previous drilldown depth so we can tell whether the next
 * `applyWrapperTransform` call is a drill-in (depth grew) or a drill-back
 * (depth shrunk) — the answer drives the keyframe direction (slide-in
 * from right vs slide-in from left).
 */
let lastDrilldownPathLength = 0;

function applyWrapperTransform( wrapper ) {
	const track = wrapper.querySelector( `.${ TRACK_CLASS }` );
	if ( ! track ) {
		return;
	}

	const panels = Array.from(
		track.querySelectorAll( `:scope > .${ PANEL_CLASS }` )
	);

	const newLength = state.drilldownPath.length;
	const direction = newLength < lastDrilldownPathLength ? 'back' : 'in';
	lastDrilldownPathLength = newLength;

	const activeId = newLength === 0
		? null
		: state.drilldownPath[ newLength - 1 ];

	panels.forEach( ( panel ) => {
		const isActive = activeId === null
			? panel.dataset.level === '0'
			: panel.id === activeId;
		if ( isActive ) {
			panel.setAttribute( 'data-active', 'true' );
			panel.setAttribute( 'data-direction', direction );
		} else {
			panel.removeAttribute( 'data-active' );
			panel.removeAttribute( 'data-direction' );
		}
	} );
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
 * Sync every parent link's `aria-expanded` attribute to the current drill
 * path — authoritative single source of truth.
 *
 * For each parent-item link in the track: look up its lazily-built panel
 * in `childPanelCache`, and set `aria-expanded` to `true` iff that panel's
 * id is currently on `state.drilldownPath`. Links whose panels haven't been
 * built yet (never drilled into) stay at `false`.
 *
 * Computing this from the path on every transition is more robust than
 * flipping individual elements, because it stays correct for any
 * future interaction that pops or pushes more than one level at a time.
 *
 * @param {HTMLElement} track The `.ktc-drilldown__track` element.
 * @return {void}
 */
function syncChevronExpanded( track ) {
	const triggerLinks = track.querySelectorAll(
		'li[data-drilldown-parent="true"] > a[aria-expanded]'
	);
	triggerLinks.forEach( ( link ) => {
		const li = link.parentElement;
		const panel = li ? childPanelCache.get( li ) : null;
		const expanded = !! (
			panel && state.drilldownPath.includes( panel.id )
		);
		link.setAttribute( 'aria-expanded', expanded ? 'true' : 'false' );
	} );
}

/**
 * Mark every panel in the track either active (focusable, visible to
 * assistive tech) or inactive (`inert` + `aria-hidden="true"`).
 *
 * Every non-active panel — ancestors AND siblings of the active panel —
 * gets both `inert` and `aria-hidden`, so keyboard focus and assistive
 * tech cursors can never escape to off-screen items regardless of how
 * deep the drill path is. At level N, panels 0..N-1 are all inert.
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
 * Move focus to the back button in the panel header.
 *
 * Deliberately does NOT focus arbitrary first-list links. Parent items
 * carry `data-drilldown-bound` click handlers; focusing one after a tap
 * causes mobile browsers to deliver a ghost click to it (the browser
 * resolves the touch gesture against whatever has focus), which would
 * skip a level or create an infinite drill-back loop.
 *
 * @param {HTMLElement} panel The panel to focus into.
 * @return {void}
 */
function focusFirstIn( panel ) {
	// CLIENT-DISABLED — uncomment to prefer the View-Parent link as the
	// initial focus target when it's present. See createViewParentItem.
	// const viewParent = panel.querySelector( `.${ VIEW_PARENT_CLASS } a` );
	// if ( viewParent ) {
	// 	viewParent.focus();
	// 	return;
	// }
	const back = panel.querySelector( `.${ BACK_BUTTON_CLASS }` );
	if ( back ) {
		back.focus();
	}
}

/**
 * Drill back one level: pop the path stack, reverse the slide, and
 * update inertness.
 *
 * Focus is intentionally NOT moved. The originating trigger lives in the
 * panel we just left, which is about to become `inert` — focusing into an
 * inert subtree is a no-op anyway, and on iOS the focus call could cause
 * a synthetic click to re-drill the panel we just popped.
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

	const wrapper = track.closest( `.${ WRAPPER_CLASS }` );
	// Re-entry guard: if a slide is already in flight, drop the call.
	// The CSS pointer-events: none on .ktc-is-drilling already blocks
	// real taps; this catches anything that bypassed hit-testing
	// (synthetic events, dispatched click(), keydown shortcuts).
	if ( wrapper && wrapper.classList.contains( DRILLING_CLASS ) ) {
		return;
	}

	activateDrillGuard( track );
	state.drilldownPath.pop();

	if ( wrapper ) {
		applyWrapperTransform( wrapper );
	}

	// CRITICAL ordering: focus must leave the leaving panel BEFORE that
	// panel becomes inert. Otherwise the browser kicks focus to <body>
	// (outside the modal) and core/navigation's focus-out handler closes
	// the overlay.
	//
	// We can't simply focus the new active panel here because it is still
	// inert at this point (it was inerted by the previous drillIn when it
	// became the inactive panel). Focusing into an inert subtree is a
	// silent no-op, leaving the original focus on the soon-to-be-inert
	// back button. So:
	//
	//   1. Focus the wrapper (always non-inert, tabindex="-1"). This
	//      moves focus out of the leaving panel safely.
	//   2. Run updatePanelInertness. Now the new active panel is no
	//      longer inert; the leaving panel becomes inert, but focus is
	//      already on the wrapper so nothing gets kicked.
	//   3. Best-effort move focus into the new active panel's back
	//      button for keyboard users.
	if ( wrapper ) {
		wrapper.focus( { preventScroll: true } );
	}

	updatePanelInertness( track );
	syncChevronExpanded( track );

	const newActive = getActivePanel( track );
	if ( newActive ) {
		const target =
			newActive.querySelector( `.${ BACK_BUTTON_CLASS }` ) ||
			newActive.querySelector( 'a, button' );
		if ( target ) {
			target.focus( { preventScroll: true } );
		}
	}
}

/**
 * Drill in to a child panel: push its id on the path stack, animate the
 * panel reveal, update inertness, and move focus.
 *
 * @param {HTMLElement} panel The panel being drilled into.
 * @return {void}
 */
function drillIn( panel ) {
	const track = panel.closest( `.${ TRACK_CLASS }` );
	const wrapper = panel.closest( `.${ WRAPPER_CLASS }` );
	if ( ! track || ! wrapper ) {
		return;
	}
	// Re-entry guard: if a slide is already in flight, drop the call.
	// The CSS pointer-events: none on .ktc-is-drilling already blocks
	// real taps; this catches anything that bypassed hit-testing
	// (synthetic events, dispatched click(), keydown shortcuts).
	if ( wrapper.classList.contains( DRILLING_CLASS ) ) {
		return;
	}

	activateDrillGuard( track );
	state.drilldownPath.push( panel.id );

	applyWrapperTransform( wrapper );
	updatePanelInertness( track );
	syncChevronExpanded( track );
	focusFirstIn( panel );
}

/**
 * Chevron click handler — materialises the target child panel (if not
 * already cached) and drills into it.
 *
 * @param {HTMLElement} parentLi The parent list item whose chevron was clicked.
 * @return {void}
 */
function onChevronClick( parentLi ) {
	const panel = buildChildPanel( parentLi );
	if ( ! panel ) {
		return;
	}
	drillIn( panel );
}


/**
 * Wire each tagged direct-child parent `<li>` of the given `<ul>`:
 *   1. Append a decorative chevron `<span>` (visual only, aria-hidden).
 *   2. Make the parent `<a>` the drill-down trigger — clicking it opens
 *      the submenu instead of navigating. The actual page link is kept
 *      reachable via the synthesised "View [Parent]" item inside the panel.
 *
 * Idempotent: `<li>`s that already have a chevron span are skipped, and
 * links that already have `data-drilldown-bound` are not re-bound.
 * The click handler guards with `isBelowBreakpoint()` so desktop
 * navigation remains unaffected when the overlay is displayed above the
 * breakpoint (e.g. due to forced visibility).
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
		let chevron = li.querySelector( `:scope > .${ CHEVRON_CLASS }` );
		if ( ! chevron ) {
			chevron = createChevronDecoration( arrowSrc );
			li.appendChild( chevron );
		}
		if ( ! chevron.dataset.drilldownBound ) {
			chevron.dataset.drilldownBound = 'true';
			chevron.addEventListener( 'click', ( event ) => {
				if ( ! isBelowBreakpoint() ) {
					return;
				}
				event.preventDefault();
				event.stopPropagation();
				onChevronClick( li );
			} );
		}

		const link = li.querySelector( ':scope > a' );
		if ( link && ! link.dataset.drilldownBound ) {
			link.dataset.drilldownBound = 'true';
			link.setAttribute( 'aria-expanded', 'false' );
			link.addEventListener( 'click', ( event ) => {
				if ( ! isBelowBreakpoint() ) {
					return;
				}
				event.preventDefault();
				event.stopPropagation();
				onChevronClick( li );
			} );
		}
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

	// Defensive guard for multi-navigation pages: if the overlay has a
	// zero bounding box, an ancestor is `display: none` (the theme hides
	// the desktop nav's markup on mobile viewports via its own class).
	// Skip those overlays so the desktop nav's hidden DOM never gets
	// wrapped, tagged, or chevroned — even if its `is-menu-open` class
	// were somehow toggled by a third party.
	const rect = overlay.getBoundingClientRect();
	if ( rect.width === 0 && rect.height === 0 ) {
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
 * Keeps the wrapper, built panels, and chevrons in place so reopening
 * the overlay is instant. Only the drill path, panel inertness, and
 * `aria-expanded` states are rewound. Idempotent — safe to call on
 * overlays that never had a wrapper injected.
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
	applyWrapperTransform( wrapper );
	updatePanelInertness( track );
	syncChevronExpanded( track );
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
 * Initialise observers for every navigation overlay currently in the DOM.
 *
 * @return {void}
 */
function init() {
	const overlays = document.querySelectorAll( OVERLAY_SELECTOR );
	overlays.forEach( observeOverlay );
}

if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', init );
} else {
	init();
}

/**
 * Contact-form trigger: any anchor whose href ends with
 * `#kt-form-contact` opens the same enquiry modal that the
 * `wp-block-kate-and-toms-icon-button` (with `showForm: true`,
 * `formType: contact`) opens in the desktop header.
 *
 * Editors add a Custom Link menu item with URL `#kt-form-contact`
 * to the mobile burger menu so it can call the contact form without
 * the icon-button block (which is parent-locked to core/navigation
 * and awkward to drop into a menu via Appearance → Menus).
 *
 * The icon-button stays in the desktop nav DOM at all viewports
 * (only hidden via CSS on mobile), so triggering its `click()`
 * fires its existing jQuery handler and the modal opens. We attach
 * the listener at document level so it works regardless of where
 * the link lives or when it is injected into the DOM.
 */
const CONTACT_FORM_HASH = '#kt-form-contact';
const CONTACT_FORM_TRIGGER_SELECTOR =
	'.wp-block-kate-and-toms-icon-button[data-show-form="true"][data-form-type="contact"]';

document.addEventListener( 'click', ( event ) => {
	if ( ! ( event.target instanceof Element ) ) {
		return;
	}
	const link = event.target.closest( `a[href$="${ CONTACT_FORM_HASH }"]` );
	if ( ! link ) {
		return;
	}
	const trigger = document.querySelector( CONTACT_FORM_TRIGGER_SELECTOR );
	if ( ! trigger ) {
		return;
	}
	event.preventDefault();
	event.stopPropagation();

	// Close any open burger overlay BEFORE triggering the modal. The
	// focus trap at the top of this file intercepts focusout events
	// while the overlay carries `is-menu-open` — without closing it
	// first, the trap bounces focus back to the wrapper every time
	// the user tries to focus an input inside the form modal,
	// leaving the form visible but uninteractive.
	const openOverlay = document.querySelector(
		`${ OVERLAY_SELECTOR }.${ OVERLAY_OPEN_CLASS }`
	);
	if ( openOverlay ) {
		const closeBtn = openOverlay.querySelector(
			'.wp-block-navigation__responsive-container-close'
		);
		if ( closeBtn ) {
			closeBtn.click();
		}
		// Yield a frame so core's interactivity-driven re-render has
		// removed `is-menu-open` before the modal opens — the trap
		// gates on that class, and a stale value would still trap.
		requestAnimationFrame( () => trigger.click() );
		return;
	}

	trigger.click();
} );

/**
 * Search-house-name trigger: a Custom Link menu item with URL
 * `#kt-search-house-name` opens a mobile-only search overlay that
 * reuses the existing autocomplete-search block.
 *
 * Implementation: instead of duplicating the search UI / store /
 * REST plumbing, we MOVE the page's existing
 * `.wp-block-kate-toms-core-autocomplete-search` element into a
 * fixed-position overlay div appended to `<body>`. The element's
 * Interactivity API bindings travel with it, so typing in the
 * overlay's input drives the same store, the same `/wp-json/
 * kate-toms/v1/autocomplete-search` call, the same grouped
 * results, and the same navigation behaviour as the desktop
 * search.
 *
 * On close (X / Escape) the element is moved back to its original
 * location so the desktop nav continues to render it normally on
 * resize / next page load.
 */
const SEARCH_HASH = '#kt-search-house-name';
const SEARCH_BLOCK_SELECTOR = '.wp-block-kate-toms-core-autocomplete-search';
const SEARCH_INPUT_SELECTOR = '.autocomplete-search__input';
const MOBILE_SEARCH_OPEN_CLASS = 'kt-mobile-search-open';
const MOBILE_SEARCH_OVERLAY_CLASS = 'kt-mobile-search-overlay';

let searchOriginalParent = null;
let searchOriginalNextSibling = null;

function ensureSearchOverlay() {
	let overlay = document.querySelector(
		`.${ MOBILE_SEARCH_OVERLAY_CLASS }`
	);
	if ( overlay ) {
		return overlay;
	}
	overlay = document.createElement( 'div' );
	overlay.className = MOBILE_SEARCH_OVERLAY_CLASS;
	overlay.setAttribute( 'role', 'dialog' );
	overlay.setAttribute( 'aria-modal', 'true' );
	overlay.setAttribute( 'aria-label', 'Search houses by name' );

	const close = document.createElement( 'button' );
	close.type = 'button';
	close.className = `${ MOBILE_SEARCH_OVERLAY_CLASS }__close`;
	close.setAttribute( 'aria-label', 'Close search' );
	close.textContent = '×';
	close.addEventListener( 'click', closeMobileSearch );
	overlay.appendChild( close );

	document.body.appendChild( overlay );
	return overlay;
}

function openMobileSearch() {
	if ( document.body.classList.contains( MOBILE_SEARCH_OPEN_CLASS ) ) {
		return;
	}
	const searchBlock = document.querySelector( SEARCH_BLOCK_SELECTOR );
	if ( ! searchBlock ) {
		return;
	}

	searchOriginalParent = searchBlock.parentElement;
	searchOriginalNextSibling = searchBlock.nextElementSibling;

	const overlay = ensureSearchOverlay();
	overlay.appendChild( searchBlock );
	document.body.classList.add( MOBILE_SEARCH_OPEN_CLASS );

	const input = searchBlock.querySelector( SEARCH_INPUT_SELECTOR );
	if ( input ) {
		input.focus();
	}
}

function closeMobileSearch() {
	if ( ! document.body.classList.contains( MOBILE_SEARCH_OPEN_CLASS ) ) {
		return;
	}
	const searchBlock = document.querySelector( SEARCH_BLOCK_SELECTOR );
	if ( searchBlock && searchOriginalParent ) {
		if ( searchOriginalNextSibling ) {
			searchOriginalParent.insertBefore(
				searchBlock,
				searchOriginalNextSibling
			);
		} else {
			searchOriginalParent.appendChild( searchBlock );
		}
	}
	searchOriginalParent = null;
	searchOriginalNextSibling = null;
	document.body.classList.remove( MOBILE_SEARCH_OPEN_CLASS );
}

document.addEventListener( 'click', ( event ) => {
	if ( ! ( event.target instanceof Element ) ) {
		return;
	}
	const link = event.target.closest( `a[href$="${ SEARCH_HASH }"]` );
	if ( ! link ) {
		return;
	}
	event.preventDefault();
	event.stopPropagation();

	// Close burger overlay first (same reason as #kt-form-contact —
	// the focus trap intercepts focusout while is-menu-open is set
	// and would prevent the search input from receiving focus).
	const openOverlay = document.querySelector(
		`${ OVERLAY_SELECTOR }.${ OVERLAY_OPEN_CLASS }`
	);
	if ( openOverlay ) {
		const closeBtn = openOverlay.querySelector(
			'.wp-block-navigation__responsive-container-close'
		);
		if ( closeBtn ) {
			closeBtn.click();
		}
		requestAnimationFrame( openMobileSearch );
		return;
	}

	openMobileSearch();
} );

document.addEventListener( 'keydown', ( event ) => {
	if (
		event.key === 'Escape' &&
		document.body.classList.contains( MOBILE_SEARCH_OPEN_CLASS )
	) {
		closeMobileSearch();
	}
} );
