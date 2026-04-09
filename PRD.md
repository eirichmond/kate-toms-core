# PRD: Mobile Drilldown Navigation Enhancement

> Generated: 2026-04-09
> Status: Draft

## Overview

A frontend enhancement module inside the `kate-toms-core` plugin that augments the core `core/navigation` block's mobile overlay with an iOS-style drilldown experience. Instead of rendering nested submenus as an ever-growing vertical list, any parent item with children will show a right-pointing arrow; tapping it slides a new panel in from the right containing the submenu. Users can drill arbitrarily deep, and a back affordance returns them to the parent panel. Zero configuration — it applies globally to all core Navigation blocks on the site below the 1100px breakpoint.

## Goals

- Replace the long, fully-expanded mobile menu with a one-panel-at-a-time drilldown that matches user expectations from native apps.
- Enhance the existing `core/navigation` block non-destructively — no new block, no theme fork, no markup replacement.
- Support arbitrary submenu depth.
- Meet WCAG 2.1 AA: focus management, keyboard parity, screen reader announcements, no gesture-only interactions.
- Zero-config: drop-in behaviour that activates only below 1100px and only inside open Navigation overlays.

## Non-Goals

- **No custom block.** We do not register a new navigation block or wrap the core one.
- **No desktop-nav changes.** The ≥1100px navigation keeps its existing behaviour.
- **No admin UI / settings page.** Breakpoint, animation duration, and styling are fixed constants.
- **No physical drag gestures.** Drilldown is tap-driven (answer A in discovery). No swipe-to-drag panel implementation.
- **No classic-menu (`wp_nav_menu()`) support.** The site uses the core Navigation block everywhere relevant; classic menus are out of scope.
- **No replacement of the core overlay open/close mechanism.** We extend the `core/navigation` Interactivity store, not replace it.
- **No content model changes.** No CPTs, taxonomies, options, meta, or DB tables.
- **No i18n strings beyond the few required UI labels** (e.g. "Back", "View [parent]"). These will be translation-ready via the plugin's existing `kate-toms-core` text domain.

## Technical Requirements

### Environment

- **PHP**: 8.1+ (matches site runtime per root CLAUDE.md).
- **WordPress**: 6.8.2 (site version). Requires WP ≥ 6.5 for stable `wp_register_script_module` / `wp_enqueue_script_module` + Interactivity API store extension.
- **Dependencies**:
  - Runtime: `@wordpress/interactivity` (provided by core).
  - Dev: plugin already has `@wordpress/scripts` — reuse existing build pipeline.
  - No new Composer or npm packages.

### Architecture

- **OOP**, following the plugin's existing WP Boilerplate pattern. New class `Kate_Toms_Core_Mobile_Nav` under `includes/mobile-nav/class-kate-toms-core-mobile-nav.php`, wired into the loader in `class-kate-toms-core.php` alongside the other `define_public_hooks()` registrations.
- **Autoloading**: manual `require_once` in the main plugin bootstrap, consistent with the rest of `kate-toms-core` (no PSR-4 in this plugin today — don't introduce it for one feature).
- **Namespace**: none (plugin uses class-name prefixing, not namespaces). Class prefix: `Kate_Toms_Core_`.
- **Frontend code**: a single Interactivity API view script **module** registered as `kate-toms-core/mobile-nav-drilldown`, source at `blocks/../modules/mobile-nav-drilldown/view.js` (or a new top-level `modules/` directory inside the plugin — to be finalised in tasks). Built via the existing `npm run build` pipeline. It imports `store` from `@wordpress/interactivity` and calls `store( 'core/navigation', { … } )` to **extend** the core navigation store with new state/actions rather than register a new namespace.
- **Styles**: a single CSS file `assets/css/mobile-nav-drilldown.css` loaded only when needed (see "Frontend Output"). No Sass — follow the theme's existing plain-CSS block-assets convention.
- **Breakpoint**: hardcoded `1100px` constant shared between CSS (`@media (max-width: 1100px)`) and JS (`matchMedia`). Document the constant in one place as the source of truth.

### Data Model

- None. No custom post types, taxonomies, tables, options, user meta, or post meta.

### Roles and Capabilities

- None.

## Features

### 1. Drilldown Panel Transform

**Description**: Intercepts nested submenus inside an open core navigation overlay and presents each level as a horizontally-sliding panel.
**User-facing**: Frontend only, mobile breakpoint only.
**Details**:
- On overlay open (detected by extending `core/navigation` store's `actions.openMenu` / reacting to the existing `isMenuOpen` state), the view module scans the overlay for `li` items containing a nested `ul` (i.e. `core/navigation-submenu`).
- Each such parent gets a right-pointing chevron button injected as a sibling of the link, acting as the drilldown trigger. The parent link itself remains a normal link.
- Tapping the chevron pushes a new panel into view via `translateX` on a stacking context. The previous panel stays mounted (instant back) but is `aria-hidden="true"` and `inert`.
- Panel header shows a back button (labelled with the parent's text) and pushes the user back one level when tapped.
- The **first item inside each drilled panel** is a synthesised "View [Parent Label]" link that points to the parent's own URL, so the parent destination remains reachable (answer to discovery Q5).
- Arbitrary depth: the same logic recurses for any `core/navigation-submenu` nested inside another.

### 2. Breakpoint Gating

**Description**: Drilldown behaviour and styles are only active below 1100px.
**User-facing**: Frontend.
**Details**:
- CSS rules live inside `@media (max-width: 1100px)` only.
- JS uses `window.matchMedia('(max-width: 1100px)')` and attaches a `change` listener. Above the breakpoint, any transform/inert state is reset and the menu renders normally.
- On resize across the breakpoint while the overlay is open, state is cleaned up gracefully (reset to level 0, remove `inert`).

### 3. Animation

**Description**: Panels slide using a CSS `transform: translateX()` transition.
**User-facing**: Frontend.
**Details**:
- Duration: **0.3s**, easing: `cubic-bezier(0.4, 0, 0.2, 1)` (material "standard").
- Respects `prefers-reduced-motion: reduce` — transitions become `0s` and panels swap instantly.
- GPU-friendly (`translateX` + `will-change: transform` only on the active transition).

### 4. Parent Logo / Overlay Header Stability

**Description**: The site logo and overlay close (×) button remain in place at the top of the overlay across all drill levels (answer to discovery Q6).
**User-facing**: Frontend.
**Details**:
- The drilldown container is scoped to the `<ul>` region of the overlay, not the entire overlay. The core Navigation block's overlay header (logo + close) is untouched.
- The back button appears **inside** the drilled panel, above the submenu list — not in the overlay header.

### 5. Accessibility

**Description**: Full WCAG 2.1 AA compliance for the drilldown interaction.
**User-facing**: Frontend.
**Details**:
- **Focus management**: drilling in moves focus to the "View [Parent]" link (first focusable item) of the new panel; drilling back returns focus to the chevron button that opened it.
- **`inert` + `aria-hidden`**: inactive panels get both, so screen readers and keyboard focus can't land on off-screen items.
- **Keyboard parity**:
  - `Enter` / `Space` on a chevron button → drill in.
  - `Escape` on a drilled panel → drill back one level. At level 0, `Escape` falls through to the core block's own close-overlay handler.
  - `Right Arrow` on a parent row → drill in. `Left Arrow` inside a drilled panel → drill back. (Implemented only when the focused element is a menu item; doesn't interfere with typing.)
- **ARIA**: chevron buttons have `aria-label="Show submenu for {parent}"` and `aria-expanded` reflecting panel state. Back button is a `<button>` with `aria-label="Back to {grandparent or top}"`.
- **Live region**: an `aria-live="polite"` off-screen element announces level changes ("In submenu: Houses") for screen reader users.
- **No gesture-only interactions** — everything is reachable by tap/click and keyboard.

### 6. Non-Parent Items Unchanged

**Description**: Menu items without children render and behave exactly as they do today (answer to discovery Q9).
**User-facing**: Frontend.
**Details**:
- Detection is structural (presence of a nested `ul` inside the `li`), so leaf items are untouched — no chevron, no modified markup.

## Admin UI

- None. Zero-config by design.

## Frontend Output

- No new blocks, shortcodes, or template tags.
- A **view script module** `kate-toms-core/mobile-nav-drilldown` is registered via `wp_register_script_module()` and **enqueued conditionally** via `wp_enqueue_script_module()` only when the page actually renders a `core/navigation` block. Wire this via:
  - `render_block_core/navigation` filter — on first invocation per request, call `wp_enqueue_script_module()` and enqueue the CSS with `wp_enqueue_style()`. This keeps the enhancement off pages that don't have a nav (e.g. some landing pages).
- The module extends the existing `core/navigation` Interactivity store; no custom `data-wp-interactive` directive is required on the markup since the core block already sets one.
- Markup mutations (chevron buttons, synthesised "View [Parent]" links, panel wrappers) are applied **client-side on overlay open**, not server-side. Rationale: keeps the core block's rendered HTML canonical, avoids PHP block-filter complexity, and means the enhancement is fully reversible if disabled.

## REST API

- None.

## Third-Party Integrations

- None.

## Scheduled Tasks

- None.

## CLI Commands

- None.

## Lifecycle

- **Activation**: no-op. Nothing to install.
- **Deactivation**: no-op. The module simply stops being enqueued; the core Navigation block reverts to its default overlay behaviour with no residue.
- **Uninstall**: no-op. No data to clean up.

## Testing Strategy

- **Unit tests**: not a strong fit — logic is overwhelmingly DOM-manipulation and state transitions in the view module. Skip unit tests for this feature.
- **Integration / E2E tests** (Playwright via `@wordpress/scripts`):
  - Overlay opens below 1100px → chevrons appear on parent items only.
  - Tapping a chevron slides a panel in and moves focus to the "View [Parent]" link.
  - Back button returns focus to the originating chevron.
  - Three-level drill (e.g. Top → Houses → Cotswolds → Luxury) works and all levels can be navigated back out in sequence.
  - `Escape` inside a drilled panel drills back; `Escape` at level 0 closes the overlay.
  - Resize across the 1100px breakpoint while drilled → state resets cleanly.
  - `prefers-reduced-motion: reduce` → transitions resolve instantly.
  - Leaf menu items render without chevrons and navigate as plain links.
  - Close + reopen the overlay → drilldown state resets to level 0.
- **Manual accessibility checks**:
  - VoiceOver on iOS Safari announces level changes and back-button context.
  - NVDA + Firefox keyboard-only walkthrough reaches every item at every depth.
  - Axe DevTools on an open, drilled overlay reports zero violations.
- **Cross-browser smoke**: iOS Safari, Android Chrome, desktop Chrome/Firefox/Safari resized to <1100px.

## Open Questions

- **Back-button label wording**: "Back" alone, "Back to [grandparent]", or just the grandparent label with a chevron icon? Recommendation: `← [Grandparent label]`, falling back to `← Menu` at level 1. Confirm during implementation.
- **Chevron icon source**: inline SVG in the view module (preferred — no extra request, themeable via `currentColor`), or a reused icon from the theme's assets? Recommendation: inline SVG.
- **Multiple Navigation blocks on one page**: the header contains two (desktop + mobile style variants). The enhancement should only activate on overlays that are actually open at <1100px, which naturally scopes it to the mobile one, but we should explicitly verify no chevrons leak into the hidden desktop nav during initialisation.
- **File location for the view module**: does it live under `blocks/` (unusual — it's not a block), under a new top-level `modules/` directory, or under `public/modules/`? Recommendation: new `modules/mobile-nav-drilldown/` at plugin root, mirroring the `blocks/` convention. Confirm before task generation.
- **Build pipeline**: `@wordpress/scripts` default entry scanning uses `--source-path=blocks`. We may need a second build command or an expanded `webpack.config.js` to include `modules/`. To be resolved in the first implementation task.
