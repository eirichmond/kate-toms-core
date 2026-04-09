# Task List: Mobile Drilldown Navigation Enhancement

> Generated from: wp-content/plugins/kate-toms-core/PRD.md
> Date: 2026-04-09

## Overview

Build a non-destructive frontend enhancement inside `kate-toms-core` that extends the core `core/navigation` block's mobile overlay with an iOS-style drilldown: chevrons on parents, panels that slide in via `translateX`, tap-only interactions, arbitrary depth, full WCAG 2.1 AA. Implementation is a single Interactivity API view script module + one CSS file, enqueued only when a `core/navigation` block renders on the page and only applied below 1100px. The view module lives inside `blocks/mobile-nav-drilldown/` so it's picked up by the existing `wp-scripts --source-path=blocks` pipeline via a minimal `block.json` — no custom webpack config required. All work lands on the pre-created `feature/mobile-drilldown-enhancement` branch.

## Prerequisites

- [ ] Confirm you are on branch `feature/mobile-drilldown-enhancement` before starting any task.
- [ ] Confirm `npm install` has been run recently in `wp-content/plugins/kate-toms-core` (the plugin already has `@wordpress/interactivity` and `@wordpress/scripts` installed).
- [ ] **Back button label** convention is locked: `← Menu` at level 1, `← [Grandparent]` deeper. Used in task 5.3.
- [ ] **Chevron icon asset** is locked: use the existing `public/assets/images/right-arrow.png`. Used in task 4.2 (right-facing) and task 5.3 (same image rotated 180° via CSS for the back button).

## 1. Scaffolding & Build Pipeline

- [x] **1.1** Create `blocks/mobile-nav-drilldown/` inside the plugin with: `block.json`, `view.js`, `style.css`, and a short `README.md` describing the module's purpose in two sentences. The `block.json` is a minimal stub — name `kate-toms-core/mobile-nav-drilldown`, `apiVersion: 3`, `viewScriptModule: "file:./view.js"`, `style: "file:./style.css"`, and `"render": false` (or no render callback) because this is not a real block, only a build discovery hook.
  - What: Piggyback on the existing `wp-scripts --source-path=blocks` discovery so no custom webpack config is needed. The block is never registered in PHP, only its view module + style are used.
  - Test: `ls blocks/mobile-nav-drilldown/` shows the four files.

- [x] **1.2** Run `npm run build` and verify the pipeline produces `build/mobile-nav-drilldown/view.js` and `build/mobile-nav-drilldown/style-view.css`, plus a generated `view.asset.php` file next to them. (Note: wp-scripts emits the CSS as `style-view.css` because the JS entry is named `view`. Required a one-line `import './style.css';` inside `view.js` so webpack's CSS extractor picks it up — the stub block.json's `style` field alone doesn't trigger extraction without an `index.js` entry.)
  - What: Confirm wp-scripts picks up the new directory via its block.json without any config changes.
  - Test: Build completes with zero errors. Built files exist at the paths above.

- [x] **1.3** Populate `blocks/mobile-nav-drilldown/view.js` with a minimal stub that imports `store` from `@wordpress/interactivity` and calls `store( 'core/navigation', {} )` with no state/actions. Add a `console.debug('[mobile-nav-drilldown] loaded')` for smoke verification.
  - What: Confirm the view module loads as a script module at runtime and successfully targets the core navigation store namespace.
  - Test: After task 2.x enqueues it, loading the site below 1100px and opening the mobile overlay shows the debug log in the browser console. (Parked until task 2.3 is done.)

- [x] **1.4** Explicitly prevent the stub `block.json` from registering as a usable block: do **not** call `register_block_type()` against `blocks/mobile-nav-drilldown/` anywhere. Add a one-line comment in `blocks/mobile-nav-drilldown/block.json` (or its README) explaining that this directory exists purely as a build-pipeline discovery hook.
  - What: Prevent the stub block from accidentally showing up in the inserter.
  - Test: In the block editor, search the inserter for "mobile nav drilldown" — nothing should appear.

## 2. PHP Enqueue Layer

- [x] **2.1** Define a new constant `KATE_TOMS_CORE_PLUGIN_FILE` in `kate-toms-core.php` (top of the file, alongside `KATE_TOMS_CORE_VERSION`) set to `__FILE__`. This gives the rest of the codebase a stable reference for `plugins_url()` / `plugin_dir_path()` calls regardless of which file they live in.
  - What: Resolve the PRD's plugin-file reference question so subsequent URL helpers work cleanly.
  - Test: `wp eval 'var_dump( defined( "KATE_TOMS_CORE_PLUGIN_FILE" ) && is_string( KATE_TOMS_CORE_PLUGIN_FILE ) );'` prints `bool(true)`.

- [x] **2.2** Create `includes/mobile-nav/class-kate-toms-core-mobile-nav.php` with an empty class `Kate_Toms_Core_Mobile_Nav` exposing a public `register()` method. Include the file from `kate-toms-core.php` (alongside the other `require_once` includes in the bootstrap).
  - What: Scaffolding the class so the loader has something to hook onto.
  - Test: `wp eval 'var_dump( class_exists( "Kate_Toms_Core_Mobile_Nav" ) );'` prints `bool(true)`.

- [x] **2.3** Register the view script module and style in `Kate_Toms_Core_Mobile_Nav::register()`:
  - `wp_register_script_module( 'kate-toms-core/mobile-nav-drilldown', plugins_url( 'build/mobile-nav-drilldown/view.js', KATE_TOMS_CORE_PLUGIN_FILE ), array( '@wordpress/interactivity' ), KATE_TOMS_CORE_VERSION );`
  - `wp_register_style( 'kate-toms-core-mobile-nav-drilldown', plugins_url( 'build/mobile-nav-drilldown/style-view.css', KATE_TOMS_CORE_PLUGIN_FILE ), array(), KATE_TOMS_CORE_VERSION );` (wp-scripts names the extracted CSS after the JS entry; our entry is `view.js`, hence `style-view.css` — verified in task 1.2)
  - Hook `register()` to `init`. Wire the class instantiation into `Kate_Toms_Core::define_public_hooks()` in `includes/class-kate-toms-core.php`.
  - What: Make the script module and stylesheet known to WordPress so they can be enqueued on demand.
  - Test: Page source on a nav-rendering page after task 2.4 includes both the view.js module tag and the stylesheet link.

- [x] **2.4** Add a `filter( 'render_block_core/navigation' )` callback in the same class that, on first invocation per request, calls `wp_enqueue_script_module( 'kate-toms-core/mobile-nav-drilldown' )` and `wp_enqueue_style( 'kate-toms-core-mobile-nav-drilldown' )`. Use a static flag so it only enqueues once per request.
  - What: Load the enhancement only on pages that actually render a core navigation block, and only once.
  - Test: Visit a page with a nav block below 1100px → the built view.js and CSS appear in the page source. Visit a page with no nav block (create a minimal test page if needed) → neither appears. The `console.debug` from task 1.3 fires on the nav page.

## 3. Interactivity Store Extension — State & Detection

- [x] **3.1** In `view.js`, extend `store( 'core/navigation', {} )` with a `state` object exposing `drilldownPath` (array of panel ids) and a derived `state.isDrilldown` (boolean: `path.length > 0`). Wire no actions yet.
  - What: Establish the reactive state shape the rest of the logic will mutate.
  - Test: In DevTools, after the overlay opens, manually mutate the store from the console and confirm the state is readable.

- [x] **3.2** Add a callback (using `callbacks.init` or a `data-wp-watch` equivalent attached from JS) that runs when the core nav overlay opens (`state.core.navigation.isMenuOpen === true`) and below 1100px. In that callback, query all `li` elements inside the open overlay's `ul` that directly contain a nested `ul`, and tag them with a `data-drilldown-parent="true"` attribute.
  - What: Identify parent items without modifying structure yet.
  - Test: Open the mobile overlay, inspect the DOM, and confirm every parent-with-children `li` has `data-drilldown-parent="true"` and leaf items do not.

- [x] **3.3** Gate the entire drilldown initialisation behind `window.matchMedia('(max-width: 1100px)').matches`. Attach a `change` listener that, when crossing the breakpoint, resets any drilldown state and strips the data attributes. Define the `1100` constant once at the top of `view.js`.
  - What: Ensure desktop navigation is never touched and resize-across-breakpoint behaves cleanly.
  - Test: Open overlay at 900px → attributes present. Resize browser to 1200px → the header's mobile overlay hides (existing behaviour) and the data attributes are removed. Resize back → behaviour re-initialises.

## 4. DOM Transform — Panels, Chevrons, Synthesised Parent Links

- [x] **4.1** In the init callback, wrap the top-level `<ul>` inside the overlay in a new stacking container `<div class="ktc-drilldown">` containing a flex row of panel wrappers. Move the existing `<ul>` into a `<div class="ktc-drilldown__panel" data-level="0">`. This must be idempotent — a second init must not double-wrap.
  - What: Create the viewport inside which panels will slide.
  - Test: Open overlay → markup now contains the wrapper and the root panel. Close and reopen → still only one wrapper (no nesting).

- [x] **4.2** For each `li[data-drilldown-parent]`, inject a chevron `<button class="ktc-drilldown__chevron" aria-expanded="false">` as a sibling of the link, containing an `<img>` sourced from `public/assets/images/right-arrow.png` (served from the plugin URL — pass the URL from PHP into the view module via a script-module localization or a `data-ktc-drilldown-arrow-src` attribute on the overlay's root). The `<img>` has `alt=""` (decorative — the button's `aria-label` carries meaning) and the button has `aria-label="Show submenu for {parentText}"`.
  - What: Give users the affordance to drill in, reusing the existing PNG asset.
  - Test: Each parent row visually shows a right-pointing arrow. Keyboard-tabbing through the menu stops on each chevron in order. Network tab confirms `right-arrow.png` is loaded once.

- [x] **4.3** Lazily build child panels on first drill-in: when a chevron is activated for the first time, create a new `<div class="ktc-drilldown__panel" data-level="N">` containing (a) a back button header and (b) a cloned/detached `<ul>` of the child submenu. Append it to the wrapper from 4.1. Cache the panel DOM node on the parent `li` so repeat drills reuse it.
  - What: Build drilled levels on demand, reuse across opens.
  - Test: Tap a chevron → a new panel appears in the DOM next to the root panel. Tap the same chevron again after drilling out → no duplicate panel is created (check DOM node count).

- [x] **4.4** At the top of every lazily-built child panel's cloned `<ul>`, prepend a synthesised `<li class="ktc-drilldown__view-parent"><a href="{parentHref}">View {parentLabel}</a></li>`. Skip this synthesis if the parent link has no `href` (pure non-linking parent).
  - What: Keep the parent destination reachable from inside its own submenu.
  - Test: Drill into a parent that has both a URL and children → first item is "View [Parent]" linking to the parent URL. Drill into a pure-container parent with no href → no synthesised item.

- [x] **4.5** Recursion sanity check: the same init logic must run on every nested `<ul>` so a level-2 submenu that has its own children also gets chevrons and lazily builds level-3 panels. Make sure the transform from 4.2 and 4.3 walks down recursively (or re-runs against each newly-built panel after insertion).
  - What: Support arbitrary nesting depth per PRD.
  - Test: On a 3-level menu path (e.g. Houses → Cotswolds → Luxury), verify chevrons appear at every level that has children and a level-3 panel can be opened.

## 5. Interactions — Drill In, Drill Back, Keyboard, Escape

- [x] **5.1** Implement the `drillIn` action: on chevron click, push the target panel id onto `state.drilldownPath`, apply `transform: translateX(-{level * 100%})` to the wrapper, set the previous panel's `inert` + `aria-hidden="true"`, unset those on the new panel, and move focus to the new panel's first focusable element (the "View [Parent]" link, or first menu item if no synthesised link).
  - What: The core drill-in interaction.
  - Test: Click a chevron → panel slides in, focus lands on "View [Parent]", previous panel becomes inert (keyboard Tab skips it, screen reader cannot reach it).

- [x] **5.2** Implement the `drillBack` action triggered by the back button at the top of each drilled panel. Pops one level off `state.drilldownPath`, reverses the transform and inert/aria states, and restores focus to the chevron button that originally opened the now-leaving panel (cache the triggering element on drill-in).
  - What: The matching drill-back interaction with correct focus restoration.
  - Test: Drill into level 2, press back → slides back to level 1, focus returns to the chevron of the row you drilled from.

- [x] **5.3** Render the back button inside each drilled panel's header as `<button class="ktc-drilldown__back"><img alt="" class="ktc-drilldown__back-icon" /> {label}</button>` where label is `"Menu"` at level 1 and the grandparent's label at level ≥ 2. The `<img>` reuses the same `right-arrow.png` URL from task 4.2 and is rotated 180° via CSS (`.ktc-drilldown__back-icon { transform: rotate(180deg); }`) so there's only one asset on the wire. `aria-label` on the button is `"Back to {label}"`.
  - What: Visible and accessible back affordance per PRD, reusing the single arrow asset.
  - Test: Drill to level 1 → back button reads "← Menu" with a visually left-pointing arrow. Drill to level 2 under "Houses" → back button reads "← Houses" and `aria-label` is "Back to Houses". Network tab shows `right-arrow.png` loaded only once (not twice).

- [x] **5.4** Keyboard parity:
  - `Enter` / `Space` on a chevron → drillIn (native button behaviour covers this, just make sure the chevron is a real `<button>`).
  - `Escape` inside a drilled panel → drillBack; at level 0, let the event fall through to the core navigation block's existing close handler.
  - `ArrowRight` on a focused `li` whose child is a chevron → drillIn.
  - `ArrowLeft` inside a drilled panel (on a menu item) → drillBack.
  - What: Full keyboard parity per WCAG.
  - Test: Walk through the menu keyboard-only with no mouse: tab to chevron, Enter drills in; Escape drills back; ArrowRight/ArrowLeft behave as specified; final Escape at root closes the overlay.

- [x] **5.5** Reset drilldown state to level 0 when the overlay closes (`isMenuOpen` becomes false). Strip any transform and re-enable the root panel.
  - What: Reopening the menu should always start at root.
  - Test: Drill into level 2, close the overlay, reopen → root panel is visible, no transform applied, no inert on root.

## 6. Accessibility Polish

- [ ] **6.1** Add an off-screen `aria-live="polite"` element inside the drilldown wrapper. On every drill-in/drill-back, update its text content to `"In submenu: {currentPanelLabel}"` or `"Top menu"` at level 0.
  - What: Announce level changes to screen reader users.
  - Test: With VoiceOver (macOS) active, drill into "Houses" → VO speaks "In submenu: Houses". Drill back → VO speaks "Top menu".

- [ ] **6.2** Maintain `aria-expanded` on each chevron button: `true` while its child panel is part of the active drilldown path, `false` otherwise. Update in both `drillIn` and `drillBack`.
  - What: Communicate state to assistive tech.
  - Test: Inspect a chevron after drilling into it → `aria-expanded="true"`. After drilling back → `aria-expanded="false"`.

- [ ] **6.3** Confirm `inert` is set on all non-active panels, not just the immediately previous one. (E.g. at level 3, levels 0/1/2 are all inert.) Audit `drillIn` and `drillBack` loops accordingly.
  - What: Prevent focus escape from non-visible ancestors.
  - Test: Drill to level 3, press Tab repeatedly → focus cycles only within the level-3 panel, never escapes to level 0/1/2 items.

## 7. Styles & Animation

- [ ] **7.1** Fill `assets/css/mobile-nav-drilldown.css` with the layout rules (scoped under `@media (max-width: 1100px)`): the `.ktc-drilldown` wrapper is `position: relative; overflow: hidden; width: 100%;`, the inner flex row holds panels side-by-side, each `.ktc-drilldown__panel` is `flex: 0 0 100%`. Wrapper uses `transform: translateX(var(--ktc-drilldown-offset, 0))` and a `transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1)`.
  - What: Core sliding layout.
  - Test: Drill in → panel slides smoothly over 0.3s. Drill back → slides back smoothly. No layout jank.

- [ ] **7.2** JS writes the `--ktc-drilldown-offset` custom property (e.g. `--ktc-drilldown-offset: -100%` at level 1) on the wrapper instead of setting `style.transform` directly. Update `drillIn`/`drillBack` accordingly.
  - What: Keeps the animation definition in CSS where `prefers-reduced-motion` overrides can target it.
  - Test: Drill in → computed style on the wrapper shows the transform resolving via the custom property.

- [ ] **7.3** Style the chevron button (right-pointing, `currentColor`, adequate tap target ≥44×44px), the back button (left-chevron + label, full-width tap target at the top of drilled panels), and the synthesised "View [Parent]" row (visually distinct from normal items — e.g. subtle emphasis).
  - What: Visual polish matching the overlay's existing design language.
  - Test: Visual check on an iPhone-sized viewport. All tap targets meet minimum size.

- [ ] **7.4** Add a `@media (prefers-reduced-motion: reduce)` block that sets `.ktc-drilldown` wrapper transition to `none`.
  - What: Respect motion-reduction preference per PRD.
  - Test: DevTools → Rendering → Emulate CSS media feature `prefers-reduced-motion: reduce`. Drill in → panel swaps instantly, no slide.

- [ ] **7.5** Sanity-check that no styles leak above 1100px (desktop nav must be untouched). Any selector not inside `@media (max-width: 1100px)` should be justified in a comment, or removed.
  - What: Enforce the "desktop untouched" non-goal.
  - Test: Resize browser to 1200px → desktop nav renders exactly as it did before this work (diff against a pre-feature screenshot if possible).

## 8. Edge Cases

- [ ] **8.1** Multiple navigation blocks on a page: the header template has both a desktop and a mobile navigation block. Verify init only runs against the one currently rendering the open overlay (use the actual overlay element as the query root, not `document`).
  - What: Prevent chevrons or wrappers being injected into the desktop nav DOM even though its media query hides it.
  - Test: At <1100px, open overlay → inspect the desktop nav's (hidden) markup → it has no `ktc-drilldown` wrappers or chevrons on its items.

- [ ] **8.2** Idempotency: closing and reopening the overlay must not re-wrap, re-inject chevrons, or re-synthesise "View [Parent]" links.
  - What: Avoid progressive DOM bloat.
  - Test: Open/close the overlay 5 times. DOM node count inside the overlay stays constant (check via `document.querySelectorAll('.ktc-drilldown__chevron').length`).

- [ ] **8.3** Leaf items pass-through: menu items with no children must render as plain links with no chevron and no altered behaviour.
  - What: Confirm PRD non-goal that unchanged items stay unchanged.
  - Test: Tap a top-level item that has no children → navigates directly to its URL, no drill-in attempt.

- [ ] **8.4** Pure-container parents (href="#" or missing): no "View [Parent]" link is synthesised; the parent is accessed only via drill-in.
  - What: Covered by 4.4 logic, but verify explicitly.
  - Test: Create a test menu with a no-href parent → open overlay → drill in → first item is the actual first child, not a synthesised link.

## 9. Testing

- [ ] **9.1** Add a Playwright e2e test `tests/e2e/mobile-nav-drilldown.spec.js` that: sets viewport to 375×812, visits `/`, opens the mobile overlay, asserts chevrons on parent items, drills into "Houses" (or whichever parent is present on the site), asserts focus lands on the "View Houses" link, drills back, asserts focus returns to the original chevron.
  - What: Regression coverage for the core interaction.
  - Test: `npm run test:e2e` passes.

- [ ] **9.2** Add a second Playwright test covering three-level drilldown if the live menu has a 3-deep path; otherwise skip with a `test.skip()` and a comment explaining why.
  - What: Guard arbitrary-depth behaviour.
  - Test: `npm run test:e2e` passes (or skips cleanly).

- [ ] **9.3** Add a Playwright test for the resize-across-breakpoint case: open overlay at 900px, resize to 1200px, assert the drilldown wrappers and `data-drilldown-parent` attributes are gone; resize back, assert they re-appear on next overlay open.
  - What: Regression coverage for breakpoint cleanup.
  - Test: `npm run test:e2e` passes.

- [ ] **9.4** Manual accessibility pass:
  - VoiceOver on iOS Safari walking 3 levels deep.
  - NVDA + Firefox keyboard-only walkthrough.
  - Axe DevTools scan on an open, drilled overlay → zero violations.
  - What: PRD a11y requirement.
  - Test: All three pass with no criticals.

- [ ] **9.5** Cross-browser smoke: iOS Safari, Android Chrome (real device or BrowserStack), desktop Chrome/Firefox/Safari resized to <1100px.
  - What: Catch platform-specific animation / `inert` / matchMedia quirks.
  - Test: Drilldown works on every target.

## Final Checks

- [ ] Run PHPCS — `composer run phpcs:plugin` (or `vendor/bin/phpcs wp-content/plugins/kate-toms-core/includes/mobile-nav/` from the repo root).
- [ ] Run `npm run lint:js` and `npm run lint:css` inside `wp-content/plugins/kate-toms-core`.
- [ ] Run `npm run build` and commit the resulting `build/mobile-nav-drilldown/` artifacts if this repo commits build output (check existing `build/` status in git first).
- [ ] Run `npm run test:e2e`.
- [ ] Manual smoke test: real iPhone, real Android, desktop Chrome resized.
- [ ] Verify the desktop navigation (≥1100px) is visually and functionally unchanged vs. a pre-feature screenshot.
- [ ] Confirm the stub block does not appear in the block editor inserter.
- [ ] Update `wp-content/plugins/kate-toms-core/CLAUDE.md` with a one-line note about the `blocks/mobile-nav-drilldown/` build-only stub and the `render_block_core/navigation`-gated enqueue pattern, so future sessions discover it quickly.
- [ ] All tasks committed to the `feature/mobile-drilldown-enhancement` branch as a consolidated feature branch. Do not merge to `main` until the developer explicitly asks.
