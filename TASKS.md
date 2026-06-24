# Task List: House Blueprint Onboarding Wizard

> Generated from: PRD.md
> Date: 2026-06-24

## Overview

Adds a React-powered "Blueprint" submenu page under the Houses CPT that lets staff search for a house via the CRM API, confirm a display title, and generate all draft posts (parent `houses` + 5 child `houses` posts) pre-loaded with the correct katomswold block patterns in one click. Built across three layers: a new shared CRM API class, a server-side Blueprint class with REST endpoints, and a React wizard UI compiled via the existing `@wordpress/scripts` pipeline.

---

## Git Workflow

Work is split into four sequential feature branches, each corresponding to a logical group below. Each branch gets a PR against `main` that must be reviewed and merged before the next branch is cut. Commit little and often — one commit per completed task is the target.

| Branch | Covers | PR base |
|---|---|---|
| `feature/blueprint-crm-api` | Group 1 | `main` |
| `feature/blueprint-php` | Groups 2 + 3 | `main` (after group 1 merged) |
| `feature/blueprint-react` | Groups 4 + 5 | `main` (after group 2+3 merged) |
| `feature/blueprint-tests` | Group 6 | `main` (after group 4+5 merged) |

**Branching steps per group:**
```bash
# Cut branch from latest main
git checkout main && git pull origin main
git checkout -b feature/blueprint-crm-api   # (or whichever branch)

# After each task is verified, commit it
git add includes/class-blueprint-crm-api.php
git commit -m "feat: add Kate_Toms_Blueprint_CRM_API with OAuth token caching"

# When all tasks in the group are done, open a PR
gh pr create --base main --title "Blueprint: CRM API class" --body "..."
```

**PR checklist before requesting review:**
- PHPCS passes on all changed PHP files
- JS lint passes on any changed JS files
- All tasks in the group are checked off
- Manual test described in each task confirmed in browser / WP admin

---

## Prerequisites

- [x] ~~Confirm CRM search endpoint~~ — resolved: `https://booking.kateandtoms.com/apis/properties`
- [x] ~~Confirm child post type~~ — resolved: all child posts use `houses` post type with `post_parent` set to parent
- [ ] Run `npm install` in the plugin root if `node_modules` is stale

---

## 1. CRM API Class
> **Branch: `feature/blueprint-crm-api`**

Extract the shared OAuth2 + cURL logic from `House_Calendar_Manager` into a standalone, reusable class. The Blueprint feature and any future CRM calls will use this. The existing `House_Calendar_Manager` class is left untouched.

- [x] **1.1** Create `includes/class-blueprint-crm-api.php`
  - What: New class `Kate_Toms_Blueprint_CRM_API` with the three private properties (`$api_base_url`, `$oauth_token_url`, `$oauth_auth_header`), the transient-based `get_access_token()` method, and the `refresh_access_token()` cURL method — extracted from `class-houses-calendar-availability-api.php` lines ~28–200. Add a public `request( string $endpoint, array $args = [] ): array|WP_Error` method that accepts a relative path, gets a token, and makes a cURL GET request returning decoded JSON or `WP_Error`.
  - Test: `wp eval "echo (new Kate_Toms_Blueprint_CRM_API)->get_access_token();"` — confirm a non-empty string is returned and a `kt_blueprint_api_token` transient is set in the DB.
  - Commit: `feat: add Kate_Toms_Blueprint_CRM_API with OAuth token caching`

- [x] **1.2** Add `search_houses( string $query ): array` to `Kate_Toms_Blueprint_CRM_API`
  - What: Calls `https://booking.kateandtoms.com/apis/properties` (confirmed endpoint), filtering results by `$query`. Confirm the exact query parameter name against the live API response before implementing. Returns an array of `[ 'crm_id' => int, 'crm_title' => string ]` items, or `[]` on failure.
  - Test: `wp eval "echo json_encode((new Kate_Toms_Blueprint_CRM_API)->search_houses('mill'));"` — confirm a JSON array of matching houses from the live CRM is returned.
  - Commit: `feat: add search_houses method to CRM API class`

- [x] **1.3** Load the class in `includes/class-kate-toms-core.php` → `load_dependencies()`
  - What: Add `require_once plugin_dir_path( __DIR__ ) . 'includes/class-blueprint-crm-api.php';` following the existing `require_once` pattern (after line ~150).
  - Test: Activate the plugin — confirm no PHP fatal errors in the debug log (`tail -f $(date +%Y-%m-%d)-debug.log`).
  - Commit: `chore: load Kate_Toms_Blueprint_CRM_API in core class`

> **Open PR: `feature/blueprint-crm-api` → `main`**

---

## 2. Blueprint PHP Class — Skeleton and Config
> **Branch: `feature/blueprint-php`** (cut from `main` after group 1 is merged)

- [x] **2.1** Create `includes/class-kate-toms-blueprint.php`
  - What: New class `Kate_Toms_Blueprint` with the static `$blueprint_pages` array from the PRD (all 6 keys: `parent`, `more`, `availability`, `book`, `facts`, `gallery`, each with a `patterns` array of katomswold slugs). Constructor registers `admin_menu` and `rest_api_init` actions pointing to stub methods. Add a public static `get_blueprint_pages(): array` accessor.
  - Test: `wp eval "var_dump( Kate_Toms_Blueprint::get_blueprint_pages() );"` — confirm all 6 page entries and their pattern arrays are returned.
  - Commit: `feat: add Kate_Toms_Blueprint skeleton with blueprint pages config`

- [x] **2.2** Load `Kate_Toms_Blueprint` in `includes/class-kate-toms-core.php` and wire hooks
  - What: Add `require_once` for the new class in `load_dependencies()`, then in `define_admin_hooks()` instantiate `new Kate_Toms_Blueprint()` (the constructor self-registers its hooks, matching the pattern used by other API classes).
  - Test: Activate the plugin — no fatal errors. Check `$wp_filter['admin_menu']` contains the Blueprint callback.
  - Commit: `chore: load and wire Kate_Toms_Blueprint in core class`

- [x] **2.3** Register the Blueprint admin submenu page
  - What: Implement `Kate_Toms_Blueprint::register_admin_menu()` — call `add_submenu_page( 'edit.php?post_type=houses', 'Blueprint', 'Blueprint', 'manage_options', 'house-blueprint', [ $this, 'render_admin_page' ] )`. `render_admin_page()` outputs a single `<div id="kt-blueprint-root"></div>` wrapped in a `<div class="wrap">` with a page title heading.
  - Test: Log in as admin, go to Houses — confirm "Blueprint" appears in the submenu. Click it — confirm a page with `<div id="kt-blueprint-root">` in the source renders.
  - Commit: `feat: register Blueprint submenu page under Houses CPT`

- [x] **2.4** Register `crm_house_id` post meta on the `houses` post type
  - What: In `Kate_Toms_Blueprint::register_meta()` (hooked to `init`), call `register_post_meta( 'houses', 'crm_house_id', [ 'type' => 'integer', 'single' => true, 'show_in_rest' => true, 'auth_callback' => fn() => current_user_can( 'manage_options' ) ] )`.
  - Test: `wp eval "var_dump( registered_meta_keys( 'post', 'houses' )['crm_house_id'] );"` — confirm the key is registered. Also verify `GET /wp-json/wp/v2/houses/<id>` includes `meta.crm_house_id` in the response.
  - Commit: `feat: register crm_house_id post meta on houses post type`

---

## 3. REST API Endpoints
> **Branch: `feature/blueprint-php`** (continued — commit to the same branch)

All endpoints use the `kate-toms/v1` namespace, matching `class-houses-filter-api.php`. Both require `manage_options` capability.

- [x] **3.1** Register the two REST routes in `Kate_Toms_Blueprint::register_routes()`
  - What: Called by `rest_api_init`. Register `GET kate-toms/v1/blueprint/crm-search` (with a `query` string arg) and `POST kate-toms/v1/blueprint/create`. Both with `permission_callback => fn() => current_user_can('manage_options')`. Callback methods can return `[]` initially.
  - Test: `wp eval "echo rest_url('kate-toms/v1/blueprint/crm-search');"` — URL is correct. Hit it logged in as admin — confirm 200 with `[]`. Hit it logged out — confirm 401.
  - Commit: `feat: register blueprint REST routes`

- [x] **3.2** Implement the CRM search endpoint callback
  - What: `handle_crm_search( WP_REST_Request $request )` — sanitise `query` via `sanitize_text_field`. Return a 400 `WP_Error` if empty. Otherwise call `( new Kate_Toms_Blueprint_CRM_API() )->search_houses( $query )` and return a `WP_REST_Response`.
  - Test: `curl -s --cookie "wordpress_logged_in_..." "https://kateandtomsblocks.test/wp-json/kate-toms/v1/blueprint/crm-search?query=mill"` — confirm a JSON array of `{ crm_id, crm_title }` objects is returned.
  - Commit: `feat: implement CRM search REST endpoint`

- [x] **3.3** Implement duplicate detection helper
  - What: Private method `house_title_exists( string $title ): ?int` — runs a `WP_Query` for `post_type=houses`, `post_status=any`, `title` matching `$title` exactly. Returns the matching post ID or `null` if none found. Use `'exact' => true` and `'sentence' => true` on the query args.
  - Test: Create a `houses` draft manually titled "Test Mill House". `wp eval "echo (new Kate_Toms_Blueprint)->house_title_exists('Test Mill House');"` — confirm the post ID is returned. Confirm `null` for a title that doesn't exist.
  - Commit: `feat: add duplicate detection helper`

- [x] **3.4** Implement pattern content assembly helper
  - What: Private method `get_patterns_content( array $slugs ): string` — iterates `$slugs`, calls `WP_Block_Patterns_Registry::get_instance()->get_registered( $slug )` for each, concatenates the `['content']` string. Logs a `WP_DEBUG` warning and skips gracefully if a slug isn't registered.
  - Test: `wp eval "echo (new Kate_Toms_Blueprint)->get_patterns_content(['katomswold/house-title-banner','katomswold/button-widget']);"` — confirm serialised block HTML is returned containing both patterns.
  - Commit: `feat: add pattern content assembly helper`

- [x] **3.5** Implement `handle_create_blueprint` REST callback
  - What: Sanitise and validate `crm_id` (integer, required) and `display_title` (string, required, max 200 chars) from the request body. If a `force` param is absent or false, run `house_title_exists()` — return a 409 `WP_Error` with `data: [ 'existing_post_id' => int ]` if found. Otherwise: (a) `wp_insert_post()` for the parent `houses` draft with `post_title = $display_title` and `post_content = get_patterns_content( self::$blueprint_pages['parent']['patterns'] )`; (b) `update_post_meta( $parent_id, 'crm_house_id', $crm_id )`; (c) loop through the 5 child keys in `$blueprint_pages`, inserting each as a `houses` draft with `post_parent = $parent_id`, `post_name = {key}`, title per the rules below, and `post_content = get_patterns_content( $page['patterns'] )`. Return a `WP_REST_Response` with an array of `{ page_key, post_id, edit_url, title }` for all 6 posts.
  - **Title rules**: `more` → `$display_title`; all others → `"$display_title - {key} - Kate and Tom's"`.
  - Test: `curl -s -X POST -u admin:pass -H "Content-Type: application/json" -d '{"crm_id":123,"display_title":"Curl Test House"}' https://kateandtomsblocks.test/wp-json/kate-toms/v1/blueprint/create` — confirm 6 posts created. In WP admin verify: parent is `houses` type with no parent, children are `houses` type with correct `post_parent`, child slugs are `more`/`availability`/`book`/`facts`/`gallery`, `crm_house_id` meta = 123 on parent.
  - Commit: `feat: implement blueprint create endpoint with post and meta creation`

- [x] **3.6** Handle the `force: true` flag for duplicate override
  - What: When `force` is `true` in the POST body, skip the `house_title_exists()` check and proceed directly to post creation. No other change.
  - Test: Create a blueprint for "Force Test House". Run the wizard again for the same title with `force: true` in the request — confirm a second set of 6 posts is created without a 409.
  - Commit: `feat: support force flag to bypass duplicate check`

> **Open PR: `feature/blueprint-php` → `main`**

---

## 4. React Build Entry Point
> **Branch: `feature/blueprint-react`** (cut from `main` after group 2+3 is merged)

- [x] **4.1** Create `blocks/blueprint-admin/index.js`
  - What: Minimal React entry — `import { createRoot } from '@wordpress/element'; import BlueprintWizard from './components/BlueprintWizard';`. Find `document.getElementById('kt-blueprint-root')` and mount `<BlueprintWizard />`. `BlueprintWizard` is a new file at `blocks/blueprint-admin/components/BlueprintWizard.js` that renders `<p>Blueprint Wizard loading...</p>` as a placeholder.
  - Test: `npm run build` — confirm `build/blueprint-admin/index.js` is output with no errors.
  - Commit: `feat: add blueprint-admin React entry point and placeholder component`

- [x] **4.2** Enqueue the built Blueprint assets only on the Blueprint admin page
  - What: In `Kate_Toms_Blueprint::enqueue_admin_assets( string $hook )` (hooked to `admin_enqueue_scripts` in the constructor), bail early if `$hook !== 'houses_page_house-blueprint'`. Then `wp_enqueue_script( 'kt-blueprint-admin', plugins_url( 'build/blueprint-admin/index.js', dirname( __DIR__ ) . '/kate-toms-core.php' ), [ 'wp-element', 'wp-components', 'wp-api-fetch', 'wp-i18n' ], null, true )` and `wp_enqueue_style( 'wp-components' )`.
  - Test: Navigate to Houses → Blueprint — confirm "Blueprint Wizard loading..." renders inside `#kt-blueprint-root`. Navigate to any other admin page — confirm the `kt-blueprint-admin` script is NOT enqueued (check Network tab).
  - Commit: `feat: enqueue blueprint admin assets conditionally on Blueprint page`

---

## 5. React Wizard UI
> **Branch: `feature/blueprint-react`** (continued)

All components live in `blocks/blueprint-admin/components/`. State is managed with `useState` in `BlueprintWizard.js` — no external state library. Use `@wordpress/components` throughout. Use `@wordpress/api-fetch` for all REST calls.

- [x] **5.1** Build Step 1 — CRM search and title input
  - What: Replace the `BlueprintWizard` placeholder with a three-state component (`step = 1|2|3`). Step 1 renders: a `ComboboxControl` (or `TextControl` + results list) that debounces input (300ms), calls `apiFetch({ path: '/kate-toms/v1/blueprint/crm-search?query=' + value })`, and shows results as selectable options. On selection, set `crmId` and `displayTitle` state. Below it, a `TextControl` for `displayTitle` (pre-filled, editable). A `Button variant="primary"` ("Next") disabled until `crmId` is set advances `step` to 2.
  - Test: Load the Blueprint page, type a partial house name — confirm matching CRM results appear. Select one — confirm the display title field is pre-filled. Edit the title — confirm the field updates. Click Next — confirm step 2 content renders.
  - Commit: `feat: build wizard step 1 — CRM search and title input`

- [x] **5.2** Build Step 2 — Review screen
  - What: Render a summary of all 6 posts to be created — a table or list showing computed title, slug, and pattern count per page (derive from the blueprint config embedded in the JS, mirroring the PHP array). A "Back" `Button` sets `step` to 1. A "Create Blueprint" `Button variant="primary"` calls `apiFetch({ method: 'POST', path: '/kate-toms/v1/blueprint/create', data: { crm_id, display_title } })`, shows a `Spinner` while pending, transitions to step 3 on success, and surfaces errors inline on failure.
  - Test: Complete step 1 and click Next — confirm all 6 rows appear with correct computed titles and slugs. Click Back — confirm step 1 state (selected house, title) is preserved. Click "Create Blueprint" — confirm spinner appears then step 3 renders or error shows.
  - Commit: `feat: build wizard step 2 — review and create`

- [x] **5.3** Build Step 3 — Success screen
  - What: Render a confirmation heading and a list of all 6 created posts returned from the endpoint, each as an `<a>` (or `Button variant="link"`) pointing to `edit_url` with `target="_blank"`. Include a "Create Another Blueprint" `Button` that resets all state (`step=1`, `crmId=null`, `displayTitle=''`).
  - Test: Complete a full wizard run — confirm all 6 links appear with correct post titles. Click one — confirm the WP block editor opens for that draft. Click "Create Another" — confirm the wizard resets to a clean step 1 with no pre-filled values.
  - Commit: `feat: build wizard step 3 — success screen with edit links`

- [x] **5.4** Implement duplicate detection warning
  - What: When the create endpoint returns a 409, render a `Notice status="warning"` (from `@wordpress/components`) naming the existing post title (available in the `409.data.existing_post_id` response — make a `wp/v2/houses/{id}` fetch to get the title, or include the title in the 409 response body on the PHP side). Offer two `Button`s: "Change Name" (returns to step 1 with the title field focused) and "Create Anyway" (re-sends the same request with `force: true`).
  - Test: Run the wizard for an existing house title — confirm the warning notice appears with the existing post's name. Click "Create Anyway" — confirm a second set of posts is created. Click "Change Name" — confirm step 1 re-renders with the display title field focused.
  - Commit: `feat: add duplicate detection warning UI with override option`

- [x] **5.5** Implement generic error state
  - What: Wrap all `apiFetch` calls in `try/catch`. On any non-409 error, render a `Notice status="error"` with the error message and a "Try Again" `Button` that retries the failed action.
  - Test: Temporarily force a `WP_Error` return from the create endpoint. Run the wizard to "Create Blueprint" — confirm the error notice renders. Remove the forced error — confirm "Try Again" completes successfully.
  - Commit: `feat: add error notice state to wizard`

> **Open PR: `feature/blueprint-react` → `main`**

---

## 6. Testing
> **Branch: `feature/blueprint-tests`** (cut from `main` after group 4+5 is merged)

- [x] **6.1** Unit test: `Kate_Toms_Blueprint_CRM_API` token caching
  - What: In `tests/test-blueprint-crm-api.php`, test that `get_access_token()` returns a cached transient value without triggering `refresh_access_token()`. Second test case: transient returns `false` — assert `set_transient` is called with the refreshed token. Use `Brain\Monkey` or WP test suite mocking as already configured in the project.
  - Test: `vendor/bin/phpunit tests/test-blueprint-crm-api.php` — all assertions pass.
  - Commit: `test: add unit tests for CRM API token caching`

- [x] **6.2** Integration test: blueprint create produces correct post structure
  - What: In `tests/test-blueprint-create.php`, call `POST /kate-toms/v1/blueprint/create` with `{crm_id: 1, display_title: "Integration Test House"}`. Assert: 6 posts created total; parent is `houses` type with no `post_parent`; all children are `houses` type with `post_parent` equal to parent ID; child slugs are `more`, `availability`, `book`, `facts`, `gallery`; `crm_house_id` meta on parent equals `1`; all posts are `draft`. Clean up in `tearDown` via `wp_delete_post`.
  - Test: `vendor/bin/phpunit tests/test-blueprint-create.php` — all assertions pass.
  - Commit: `test: add integration test for blueprint create endpoint`

- [x] **6.3** Integration test: duplicate detection returns 409
  - What: In `tests/test-blueprint-duplicate.php`, insert a `houses` post titled "Duplicate Test House", then POST to the create endpoint with the same title. Assert HTTP status 409 and response body contains `existing_post_id`. Clean up in `tearDown`.
  - Test: `vendor/bin/phpunit tests/test-blueprint-duplicate.php` — assertion passes.
  - Commit: `test: add integration test for duplicate detection 409 response`

- [x] **6.4** Playwright e2e: full wizard smoke test
  - What: In `tests/e2e/`, add `blueprint.spec.js`. Log into `kateandtomsblocks.test/wp-admin`, navigate to Houses → Blueprint, type a partial house name, select a result, confirm the title field is pre-filled, click Next, verify the review table has 6 rows, click "Create Blueprint", assert the success screen lists 6 links, click the first link and assert the block editor loads for a `houses` draft.
  - Test: `npm run test:e2e:local` — test passes against the Valet site.
  - Commit: `test: add Playwright e2e smoke test for blueprint wizard`

> **Open PR: `feature/blueprint-tests` → `main`**

---

## Final Checks (each PR before merge)

- [ ] `npm run phpcs` on all changed PHP files — zero violations
- [ ] `npm run lint:js` on `blocks/blueprint-admin/` — zero violations
- [ ] `npm run lint:css` if any CSS is added in the blueprint entry
- [ ] PHPUnit: `vendor/bin/phpunit tests/test-blueprint-*.php`
- [ ] Playwright: `npm run test:e2e:local`
- [ ] Manual smoke test in Chrome: complete a full wizard run, verify all 6 draft posts exist with correct block content in the editor, no JS console errors
- [ ] Confirm `build/blueprint-admin/index.js` is committed (built assets are committed in this project)

---

## Resolved Assumptions

1. **REST namespace**: `kate-toms/v1` — confirmed, matching `class-houses-filter-api.php`.
2. **Child post type**: `houses` — confirmed by user.
3. **CRM search endpoint**: `https://booking.kateandtoms.com/apis/properties` — confirmed by user.
4. **"More" child title**: `$display_title` with no suffix — per PRD spec.
5. **`force: true` override**: Task 3.6/5.4 adds a `force` flag for proceeding past duplicate warnings. Adjust if a suffix-appending approach is preferred instead.
6. **Admin hook name**: `houses_page_house-blueprint` is the WP-generated `$hook` value for a CPT submenu page. Verify in task 4.2 if the asset fails to load.
7. **409 title in React**: Task 5.4 notes two options for getting the existing post title (include it in the PHP 409 response body, or fetch it client-side). Including it in the PHP response is simpler — adjust `handle_create_blueprint` in task 3.5 accordingly if preferred.
