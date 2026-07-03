# PRD: Special Offers Grid (parent) + Special Offer House (child) rebuild

> Generated: 2026-07-03
> Status: Draft

## Overview

Rebuild the `kate-toms-core/kateandtoms-special-offer-house` block so it is no longer a self-contained, front-end-fidelity block rendered via `ServerSideRender` in the editor. Instead, introduce a **parent container block** ("Special Offers Grid") whose **child blocks** are the individual special-offer houses. The parent owns collection, ordering (by offer date), and — in a later phase — the responsive rows-of-four grid with auto-fill advert placeholders. Children keep their existing settings (house typeahead search, offer text, offer date, random-placeholder mode + location) but render as compact summary cards in the editor rather than full previews.

This is delivered on a **new branch** off the plugin repo for review on a PR, in two phases: **Phase 1** (parent/child structure + date ordering + faithful front-end pattern rendering) first, then **Phase 2** (rows-of-four grid + auto-fill placeholder adverts) once Phase 1 is tested.

## Goals

- Editors place special-offer houses in sequence inside a container; the front end renders them **sorted by offer date (soonest expiry first)**, independent of editor order.
- The editor no longer mirrors the front end — each child is a fast, scannable compact summary card, edited via the existing sidebar controls. No `ServerSideRender`.
- Front-end output preserves the current fidelity: each card renders the theme pattern `house-card-search-special-offer.php` with the same per-instance metadata (offer text, offer date) and the same per-instance-safe include mechanism.
- Expired offers (offer date in the past) are automatically excluded from the front end.
- **Phase 2:** cards flow in responsive rows of four (4 / 2 / 1); any incomplete final row is topped up with location-based advert placeholders, mirroring `houses-filtered-results`.
- Manual "random placeholder" children remain supported as deliberate, real cards in the grid.

## Non-Goals

- Not changing the visual design of the front-end offer card / pattern itself (only how instances are collected, ordered, and laid out).
- Not building an admin settings page, REST endpoint, cron job, or CLI command.
- Not introducing new custom post types, taxonomies, or database tables.
- Not making the editor preview *sorted* — editor shows placement order; sorting is a front-end render concern (explicitly agreed: backend need not match front end).
- Phase 2 grid/auto-fill is **specified** here but **built after** Phase 1 lands and is tested.

## Technical Requirements

### Environment
- PHP: 8.1+ (project runs PHP 8.1; keep PHPCompatibilityWP-clean)
- WordPress: 6.8.x (block API v3, existing block build tooling)
- Dependencies: no new Composer/npm deps. Uses `@wordpress/scripts` (existing), `@wordpress/block-editor` (`InnerBlocks`, `useInnerBlocksProps`), `@wordpress/components`, `@wordpress/data`/`core-data` (existing typeahead search). Reuses `Kate_Toms_Core_Admin::get_adverts_for_location()`.

### Architecture
- **Parent/child InnerBlocks** (confirmed). New dynamic parent block collects child attributes at render time, orders and (Phase 2) groups them, and renders all output itself.
- Both blocks are dynamic (`render.php`), registered through the existing `register_kateandtoms_core_blocks()` auto-discovery in the admin class (each in its own folder under `blocks/`).
- Rendering ownership: the **parent** produces all front-end markup from child *attributes*. The **child's** `render.php` renders nothing standalone (returns empty) so children cannot self-render out of order and there is no double output. The child block is effectively a typed data container inside the editor.
- Ordering/grouping logic extracted into a **pure, testable PHP helper** (e.g. `includes/special-offers/class-special-offers-grid.php` or a namespaced function) that takes an array of child attribute sets + "now" and returns the ordered/filtered (Phase 2: chunked + padded) render list. Keeps `render.php` thin and unit-testable.
- Naming:
  - Parent: `kate-toms-core/special-offers-grid` — title "Kate & Tom's Special Offers Grid".
  - Child: `kate-toms-core/kateandtoms-special-offer-house` — **name unchanged** (preserves existing content references), behaviour rebuilt.

### Data Model
- **No** new CPTs, taxonomies, tables, options, or post/user meta.
- Uses existing `houses` post type (typeahead search, parent houses only) and the existing adverts-by-location system (`get_adverts_for_location`).
- Child block attributes (retained from current block, stored in `post_content`):
  - `selectedPostId` (number, default 0) — chosen house.
  - `offer` (string) — special offer text.
  - `offerDate` (string, ISO from `DatePicker`) — **offer expiry date** (see Features).
  - `isPlaceholder` (boolean) — manual random-placeholder mode.
  - `placeholderLocation` (string enum: `cotswolds` | `coast` | `country` | `town`).
- Parent block attributes:
  - `align` (`wide` | `full`) via supports.
  - Optional (Phase 2 / open question) heading text attribute, if the `{special-offer-header}` heading from the current pattern should live on the block rather than the surrounding pattern.

### Roles and Capabilities
- None. Standard editor capabilities to edit posts/pages.

## Features

### F1 — Parent container block ("Special Offers Grid")

**Description**: A dynamic block containing special-offer-house children via `InnerBlocks`.
**User-facing**: Yes — editor (authoring) and frontend (rendering).
**Details**:
- `edit.js` uses `InnerBlocks` / `useInnerBlocksProps` with `allowedBlocks: ['kate-toms-core/kateandtoms-special-offer-house']`.
- Optional starter `template` (e.g. one child) and an appender to add more children.
- Supports `align: ["wide","full"]` to match current pattern width.
- `render.php`:
  1. Read children from `$block->inner_blocks` (or `$block->parsed_block['innerBlocks']`) → collect each child's attributes.
  2. Pass attribute list + current site-timezone "now" to the ordering helper.
  3. **Phase 1:** render each returned card in order in a single flow (no row-fill).
  4. **Phase 2:** render as responsive rows of four with auto-fill adverts.
- Ignores the auto-generated inner-blocks `$content` string (children self-render nothing).

### F2 — Child block editor rebuild (compact card, no ServerSideRender)

**Description**: The special-offer-house child renders a compact summary in the editor instead of a full front-end preview.
**User-facing**: Yes — editor only (child produces no standalone front-end output).
**Details**:
- Remove `ServerSideRender` and `@wordpress/server-side-render` usage from `edit.js`.
- Editor body shows a compact card:
  - House mode: 🏠 house name (from `core-data` `getEntityRecord`), offer text, formatted offer date.
  - Placeholder mode: "Random placeholder — {location}".
  - Empty/unselected state: prompt to pick a house in the sidebar.
- All editing stays in the existing `InspectorControls` sidebar: typeahead house search + Clear, Offer `TextControl`, Offer Date `DatePicker`, Random Placeholder `ToggleControl`, Location `SelectControl`. Preserve current behaviour (search min 2 chars, parent houses only, etc.).
- `block.json` attributes unchanged. `render.php` returns empty string (or a tiny logged-in-only hint if placed outside a Special Offers Grid parent — open question).

### F3 — Order by offer date (expiry), hide expired

**Description**: Front-end ordering/filtering logic owned by the parent.
**User-facing**: Yes — frontend.
**Details**:
- `offerDate` = the date the offer **expires**.
- Filtering: cards whose `offerDate` is **before today** (site timezone, date-granularity, inclusive of today) are **excluded** from the front end.
- Sorting: house cards sorted **ascending by `offerDate`** (soonest expiry first). Cards with no `offerDate` and manual placeholder cards sort **after** all dated house cards (stable order preserved among them = editor order).
- Manual random-placeholder children are **never** filtered by date (they have no offer date) and always occupy a real slot.
- Timezone: use `wp_timezone()` / `current_time()` — not server default. Handle `DatePicker`'s ISO datetime string (compare on date component).

### F4 — Faithful front-end card rendering (pattern + metadata)

**Description**: Preserve current per-instance pattern rendering.
**User-facing**: Yes — frontend.
**Details**:
- Reuse the existing mechanism from the current `render.php`: for each card set up `global $post` to the house, set the `$special_offer_attributes` global (`offer`, `offerDate`), `include` `patterns/house-card-search-special-offer.php` (NOT `do_blocks('<!-- wp:pattern ... /-->')`, to avoid the registry's single-include caching that would leak the first instance's offer to all cards), then `do_blocks()` the buffered output. Restore `$post` after.
- Manual placeholder cards render the advert markup (image, no link) as today.
- Keep the existing fallback-house and invalid-house guards, adapted to the parent loop (skip invalid houses; show logged-in editor notice as appropriate).

### F5 — Rows of four + auto-fill placeholder adverts (**Phase 2**)

**Description**: Responsive grid; complete incomplete rows with location adverts.
**User-facing**: Yes — frontend.
**Details**:
- Cards laid out 4 per row on desktop, collapsing to 2 (tablet) / 1 (mobile) via CSS.
- Row-completion math keys off **4**: after building the ordered card list (including manual placeholders as real cards), if `count % 4 !== 0`, append `4 - (count % 4)` advert placeholders to fill the final row.
- Adverts sourced via `Kate_Toms_Core_Admin::get_adverts_for_location( $location_key, $needed )`, mirroring `houses-filtered-results`. **Open question:** which location drives auto-fill adverts (a parent-level location setting vs. derived) — see Open Questions.
- Reuse/align advert card markup + grid classes/breakpoints with `houses-filtered-results` (`house-card advert-placeholder`, `house-card__image`) so both grids look consistent.

### F6 — Pattern rewrite + migration of existing content

**Description**: Update the placement pattern and migrate old flat structure.
**User-facing**: Editor (pattern) + one-off migration.
**Details**:
- Rewrite `wp-content/themes/katomswold/patterns/houses-special-offers.php`: replace the hardcoded `wp:columns` + flat blocks with a single `special-offers-grid` parent containing child blocks (placeholders for the editor to fill).
- Provide a migration path for any live pages using the old structure: a block `transform`/`deprecation` and/or a one-off migration routine (via the block-editor-content-migration plugin or a `wp eval` action) that wraps existing flat `kateandtoms-special-offer-house` siblings into a `special-offers-grid` parent and drops the `wp:columns` wrapper.
- Preserve child attribute names so existing child block markup remains valid when re-parented.

## Admin UI

- No settings page, admin columns, or meta boxes.
- Editor-only: parent block with `InnerBlocks` appender; child compact card + existing sidebar `InspectorControls`.

## Frontend Output

- Parent block renders the full markup: ordered (Phase 1) then rows-of-four grid with auto-fill adverts (Phase 2).
- Each house card = theme pattern `house-card-search-special-offer.php` with per-instance offer metadata.
- Manual + auto-fill placeholder cards = advert image markup (no link).
- Expired-offer house cards omitted.

## REST API

- None. (Editor typeahead uses the existing core `houses` REST endpoint via `@wordpress/core-data`; no new routes.)

## Third-Party Integrations

- None. Adverts come from the existing internal location-based advert system (`get_adverts_for_location`).

## Scheduled Tasks

- None. Expiry is evaluated at render time, not via cron.

## CLI Commands

- None required for the block. A one-off migration may be exposed as a `do_action(...)` run via `wp eval` (see F6) rather than a registered WP-CLI command.

## Lifecycle

- **Activation**: none specific — blocks register on `init` via existing auto-discovery. No tables/options/roles.
- **Deactivation**: none — no cron/rewrites to clear.
- **Uninstall**: none — block data lives in `post_content`; nothing to purge. Deactivating the plugin leaves the block comments inert in content (standard WordPress behaviour).

## Testing Strategy

- **PHP unit (PHPUnit)** on the extracted ordering helper (pure function):
  - Sort ascending by `offerDate`; soonest expiry first.
  - Expired offers (date before today, site timezone) excluded; today's date **kept**.
  - No-date and manual-placeholder cards sort after dated cards, stable order.
  - Phase 2: chunking into fours; row-fill count `4 - (count % 4)`; exactly-multiple-of-4 adds none; all-placeholder edge cases.
  - Timezone boundary cases (e.g. offer expiring "today" near midnight in site tz).
- **Integration**: parent `render.php` reads inner-block attributes correctly; invalid/unpublished house is skipped; per-instance offer metadata is not leaked across cards (the include-not-do_blocks guarantee).
- **e2e (Playwright, local Valet)**: add a Special Offers Grid, insert several children with different dates + a manual placeholder, publish, assert front-end order (soonest first), expired omitted, and (Phase 2) rows-of-four with adverts.
- **Manual**: editor compact-card rendering; sidebar controls unchanged; pattern insertion; migration of an existing flat-structure page.

## Open Questions

- **Auto-fill advert location (Phase 2):** where does the location key for row-fill adverts come from? Options: a new parent-level location setting, inferred from the children, or a fixed default. Needs a decision before Phase 2.
- **Heading ownership:** the current pattern includes a `{special-offer-header}` heading and full-width group wrapper. Should the heading/wrapper stay in the theme pattern around the block, or become a parent block attribute/`RichText`?
- **Child placed outside a parent:** should a lone child render nothing silently, or show a logged-in-only "Place inside a Special Offers Grid" hint?
- **Expiry granularity/timezone:** confirm "expired" = strictly before today's date in site timezone (today inclusive). Confirm `DatePicker` value normalisation (date vs datetime).
- **Editor ordering cue:** editor stays in placement order — is a small "will display: {formatted date}" hint on each compact card enough, or do editors want a non-authoritative sorted preview later?
- **Migration reach:** confirm whether any live/published pages currently use the flat structure, and whether migration is one-off (script) or must also support ongoing paste of old markup (block transform/deprecation).
- **Branch & working tree:** create a new branch off the plugin repo (`kate-toms-core`) — suggested `feature/special-offers-grid`. Note: `main` currently has **uncommitted changes** to this block plus deleted `PRD.md`/`TASKS.md` and `build/` churn; decide whether to stash/commit/discard those before branching so the PR is clean (per repo convention, keep `build/` out of the PR).

## Phases (delivery order)

- **Phase 1 (build first, PR, test):** F1 parent block, F2 child editor rebuild, F3 ordering + expiry, F4 faithful pattern rendering, F6 pattern rewrite + migration, ordering-helper unit tests. Front end renders a single ordered flow (no row-fill).
- **Phase 2 (after Phase 1 tested):** F5 responsive rows-of-four + auto-fill adverts + grid CSS aligned to `houses-filtered-results`, plus chunking/row-fill unit tests.
