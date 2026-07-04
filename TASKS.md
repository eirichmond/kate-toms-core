# Task List: Special Offers Grid (parent) + Special Offer House (child) rebuild

> Generated from: PRD.md
> Date: 2026-07-03

## Overview

Rebuild the special-offer-house block into a **parent container block** ("Special Offers Grid") that holds special-offer-house **child blocks** via `InnerBlocks`. The parent owns all front-end rendering: it collects child attributes, filters expired offers, sorts by offer date, and (Phase 2) lays cards out in responsive rows of four with auto-fill advert placeholders. Delivered on branch `feature/special-offers-grid` in two phases — Phase 1 (structure + ordering + faithful render + migration) ships and is PR-tested before Phase 2 (grid + auto-fill).

## Prerequisites

- [x] **P.1** Create branch — `git checkout -b feature/special-offers-grid` off a clean `main` in `wp-content/plugins/kate-toms-core`.
  - Test: `git branch --show-current` returns `feature/special-offers-grid`; `git status` is clean.
- [x] **P.2** Confirm toolchain — `npm install` and `composer install` complete; `npm run build` succeeds on the untouched tree.
  - Test: `npm run build` exits 0 and `build/` is regenerated.
  - Note: `build/` is TRACKED (not gitignored); a full build churns every block's output. Stage only the relevant block's build files per commit and revert unrelated churn.
- [x] **P.3** Add a minimal standalone PHPUnit harness (no WordPress bootstrap needed — the ordering helper is pure PHP).
  - What: add `phpunit/phpunit` to `require-dev` in `composer.json`, a `phpunit.xml` targeting `tests/unit/`, and a `"test:unit-php"` composer script; create `tests/unit/bootstrap.php` that just `require_once`s the helper file.
  - Test: `composer install` then `vendor/bin/phpunit --version` runs; an empty test class in `tests/unit/` is discovered.

---

# PHASE 1 — Backend structure, ordering, faithful render

## 1. Ordering / filtering helper (data layer, pure PHP)

- [x] **1.1** Create the pure ordering helper.
  - What: add `includes/special-offers/class-special-offers-grid.php` with a static method (e.g. `Kate_Toms_Special_Offers_Grid::order_cards( array $children, DateTimeInterface $now, DateTimeZone $tz )`) that takes raw child attribute arrays + "now" and returns the render list — no WordPress calls, no globals.
  - Test: instantiate directly in a scratch script with sample arrays; returns an array.
- [x] **1.2** Implement filter + sort rules.
  - What: exclude house cards whose `offerDate` (date component, site tz) is **before today** (today inclusive); sort remaining house cards **ascending by `offerDate`**; keep dateless cards and manual placeholders (`isPlaceholder = true`) after dated cards in stable (input) order; never date-filter placeholders.
  - Test: covered by 1.3.
- [x] **1.3** Unit-test the helper (`tests/unit/OrderCardsTest.php` — renamed from `test-order-cards.php` for PHPUnit `*Test.php` discovery).
  - What: assert ascending sort; expired excluded; today kept; dateless/placeholder sort last & stable; empty input → empty; all-placeholder input preserved.
  - Test: `composer test:unit-php` (or `vendor/bin/phpunit`) — all green.
- [x] **1.4** Wire the helper into plugin dependency loading.
  - What: add a `require_once` for `includes/special-offers/class-special-offers-grid.php` in `load_dependencies()` in `includes/class-kate-toms-core.php`.
  - Test: `wp eval "var_dump( class_exists('Kate_Toms_Special_Offers_Grid') );"` prints `true`.

## 2. Parent block — "Special Offers Grid"

- [x] **2.1** Scaffold the parent block folder.
  - What: create `blocks/special-offers-grid/` with `block.json` (name `kate-toms-core/special-offers-grid`, title "Kate & Tom's Special Offers Grid", apiVersion 3, `supports.align: ["wide","full"]`, `render: file:./render.php`, editor/style script refs), `index.js` (registerBlockType), `edit.js`, `style.scss`, `editor.scss`.
  - Test: `npm run build` produces `build/special-offers-grid/`; block appears in the inserter (auto-registered from `build/`).
- [x] **2.2** Build the editor `InnerBlocks` UI.
  - What: `edit.js` uses `useInnerBlocksProps` / `InnerBlocks` with `allowedBlocks: ['kate-toms-core/kateandtoms-special-offer-house']`, a starter `template` of one child, and an appender.
  - Test: in the editor, insert the parent; you can only add special-offer-house children inside it.
- [x] **2.3** Parent `render.php` — collect children + order (Phase 1 flow).
  - What: read child attributes from `$block->inner_blocks` (fallback `$block->parsed_block['innerBlocks']`), pass to the ordering helper with `wp_timezone()`/`current_time()`, and render cards in a single ordered flow (no row-fill yet). Ignore the auto-generated inner-blocks `$content`.
  - Test: place 3 children with out-of-order dates; front end lists them soonest-expiry-first; an expired child is omitted.

## 3. Child block rebuild — compact card, no ServerSideRender

- [ ] **3.1** Restrict the child to the parent.
  - What: in `blocks/kateandtoms-special-offer-house/block.json`, add `"parent": ["kate-toms-core/special-offers-grid"]` (attributes otherwise unchanged).
  - Test: after build, the child no longer appears as a top-level inserter option, only inside the parent.
- [x] **3.2** Replace the editor preview with a compact summary card.
  - What: in `edit.js`, remove `ServerSideRender` + the `@wordpress/server-side-render` import; render a compact card — house mode: 🏠 house name (via `core-data` `getEntityRecord`) + offer text + formatted offer date; placeholder mode: "Random placeholder — {location}"; empty state: "Choose a house in the sidebar".
  - Test: each child shows the compact card (not a full preview); editing sidebar fields updates the card text.
- [x] **3.3** Preserve the sidebar controls unchanged.
  - What: keep the existing `InspectorControls` (typeahead search + Clear, Offer `TextControl`, Offer Date `DatePicker`, Random Placeholder `ToggleControl`, Location `SelectControl`) with current behaviour (min 2 chars, parent houses only).
  - Test: search returns parent houses; selecting one populates the card; toggling placeholder swaps to the location select.
- [x] **3.4** Make the child render nothing standalone (+ stray-child hint).
  - What: change `blocks/kateandtoms-special-offer-house/render.php` to return an empty string; if rendered outside a parent and `is_user_logged_in()`, output only the inline notice "Place this inside a Special Offers Grid."
  - Test: a child placed directly on a page renders nothing for logged-out users; logged-in sees the notice; inside a parent it produces no double output.

## 4. Faithful front-end card rendering (in parent render)

- [x] **4.1** Port the per-instance pattern-include rendering into the parent loop.
  - What: for each house card, set `global $post` to the house, set the `$special_offer_attributes` global (`offer`, `offerDate`), `include` `patterns/house-card-search-special-offer.php` (NOT `do_blocks('<!-- wp:pattern … /-->')`), `do_blocks()` the buffer, then restore `$post`.
  - Test: two children with different offers render distinct offer text (no leak of the first offer to the second).
- [x] **4.2** Port invalid/fallback-house guards + manual placeholder rendering.
  - What: skip invalid/unpublished/child-page houses (logged-in editor notice as today); render manual placeholder children as the advert image markup (image, no link).
  - Test: point a child at a trashed house → it's skipped (notice when logged in); a manual placeholder child renders its advert image.

## 5. Pattern rewrite + content migration

- [x] **5.1** Rewrite the placement pattern.
  - What: in `wp-content/themes/katomswold/patterns/houses-special-offers.php`, keep the outer full-width `wp:group` + `{special-offer-header}` heading; replace the inner `wp:columns` + flat blocks with a single `special-offers-grid` parent containing placeholder child blocks.
  - Test: insert the "Houses Special Offers" pattern; it drops in the heading + a Special Offers Grid with children.
- [x] **5.2** One-off migration routine for existing flat content.
  - What: add a `do_action('migrate_special_offers_to_grid')` handler that finds posts containing flat sibling `kateandtoms-special-offer-house` blocks (inside the old `wp:columns`), wraps them in a `special-offers-grid` parent, and removes the `wp:columns` wrapper — child attributes preserved.
  - Test: on a copied post using the old structure, run `wp eval "do_action('migrate_special_offers_to_grid');"`; post now uses the parent/child structure and renders identically (order aside).

## 6. Phase 1 verification

- [~] **6.1** Full editor + front-end smoke test. (Partially verified — see note.)
  - What: build a Special Offers Grid with several dated children (some expired), one manual placeholder; publish.
  - Test: front end shows non-expired cards soonest-first, placeholder after dated cards, each card's offer metadata correct.
  - Status: render paths (ordering, expiry exclusion, no offer leak, placeholder adverts, invalid/child-page guards, logged-in/out) verified via `do_blocks`; editor UX spot-checked in 2.2/3.2. **Outstanding:** a live publish-and-preview with mixed/expired dates + a placeholder — flagged in PR #38 as recommended before merge.
- [x] **6.2** Lint + build clean.
  - Test: `npm run lint:js`, `npm run lint:css`, `npm run phpcs` (new PHP), and `npm run build` all pass; keep `build/` churn out of the PR per repo convention.
- [x] **6.3** Open Phase 1 PR. → https://github.com/eirichmond/kate-toms-core/pull/38
  - Test: PR from `feature/special-offers-grid`; diff excludes `build/` noise; reviewer can test the ordered flow.

---

# PHASE 2 — Rows of four + auto-fill adverts (after Phase 1 is tested)

## 7. Location-agnostic advert pool

- [x] **7.1** Add an all-locations advert helper.
  - What: add `Kate_Toms_Core_Admin::get_all_adverts( $limit )` (or a small flatten in the parent render) that flattens `get_parsed_adverts()` across every location into one pool.
  - Test: `wp eval "print_r( (new Kate_Toms_Core_Admin('kate-toms-core','1.0.0'))->get_all_adverts(4) );"` returns adverts spanning locations.

## 8. Grid layout + auto row-fill

- [x] **8.1** Extend the helper with chunking + row-fill count. (Fill-count method; CSS grid handles visual row wrapping so no literal row-chunking needed.)
  - What: add a Phase 2 method (or option) that, given the ordered card list, returns rows chunked by 4 and the number of fill adverts needed (`count % 4 === 0 ? 0 : 4 - count % 4`); manual placeholders count as real cards.
  - Test: unit tests in `tests/unit/` — 4→0 fill, 5→3 fill, 8→0 fill, all-placeholder cases; `composer test:unit-php` green.
- [x] **8.2** Render rows of four with random auto-fill adverts.
  - What: in the parent `render.php`, group ordered cards into rows of 4 and append `$needed` random adverts (from 7.1) to complete the final row.
  - Test: a grid of 5 real cards renders 8 slots (5 + 3 adverts); 8 real cards render no adverts.

## 9. Grid styling

- [x] **9.1** Responsive grid CSS aligned to houses-filtered-results.
  - What: in `blocks/special-offers-grid/style.scss`, lay out 4 cards per row (desktop) → 2 (tablet) → 1 (mobile); reuse/align advert markup classes (`house-card advert-placeholder`, `house-card__image`) so both grids look consistent.
  - Test: at desktop/tablet/mobile widths the grid shows 4/2/1 columns; advert cards match the filtered-results styling.

## 10. Phase 2 verification

- [~] **10.1** Grid smoke test across counts. (Fill maths verified programmatically; live responsive eyeball outstanding.)
  - Test: verify 1–8 real cards each produce full rows of four with correct adverts; layout responsive; expired still excluded; ordering intact.
  - Status: fill/exclusion/ordering verified via `do_blocks` for 2–8 cards incl. invalid/expired/manual-placeholder mixes (all complete to multiples of four). **Outstanding:** live 4/2/1 responsive eyeball at desktop/tablet/mobile — flagged in PR #38.
- [x] **10.2** Lint + build clean; update the PR.
  - Test: `npm run lint:js`, `lint:css`, `phpcs`, `build` all pass; Phase 2 added to the PR (or a follow-up PR), `build/` kept out.

---

## Final Checks

- [ ] Run PHPCS — `npm run phpcs` (or `composer run phpcs:plugin` from site root)
- [ ] Run JS/CSS lint — `npm run lint:js` && `npm run lint:css`
- [ ] Run PHP unit tests — `vendor/bin/phpunit` (pure ordering/chunking helper)
- [ ] Run e2e if configured — `npm run test:e2e:local` (Playwright against Valet)
- [ ] Manual smoke test in browser — editor compact cards, front-end order, expiry, Phase 2 grid
- [ ] Confirm `build/` churn excluded from the PR diff
