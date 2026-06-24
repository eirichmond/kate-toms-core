# PRD: House Blueprint Onboarding Wizard

> Generated: 2026-06-24
> Status: Draft

## Overview

The Blueprint feature adds a React-powered admin wizard under the Houses CPT menu that lets non-technical staff onboard a new house property in one pass. Staff search for a house by name (via the existing CRM API), confirm or override the display title, then click Create — the wizard creates a draft parent Houses post and all five associated child pages, each pre-loaded with the correct placeholder block patterns from the katomswold theme.

## Goals

- Allow non-technical staff to onboard a new house without developer involvement
- Ensure consistent page structure and pattern placement across all properties
- Store the CRM house ID (`crm_house_id`) on the parent post so blocks and APIs can reference it
- Make the pattern-per-page configuration developer-maintainable via a PHP array, without touching the wizard UI

## Non-Goals

- Does not publish pages — all output is draft only
- Does not sync ongoing CRM data changes back to WordPress after creation
- Does not replace or delete existing house pages (duplicate handling stops the process, it does not overwrite silently)
- Does not support multisite — main site (Blog ID 1) only
- Does not migrate or touch existing houses created outside this wizard
- Does not replace the existing `House_Calendar_Manager` CRM calls — the new API class extracts shared logic; existing calls are left intact as a legacy task

## Technical Requirements

### Environment
- PHP: 8.1+
- WordPress: 6.8.2+
- Dependencies: `@wordpress/components`, `@wordpress/api-fetch`, `@wordpress/element` (already available via plugin build pipeline)

### Architecture
- OOP, namespaced under the existing plugin conventions
- New class: `Kate_Toms_Blueprint_CRM_API` — extracts OAuth2 + cURL logic from `class-houses-calendar-availability-api.php` into a reusable CRM API client
- New class: `Kate_Toms_Blueprint` — registers the admin page, REST endpoints, and page-creation logic
- New React entry point: `blocks/blueprint-admin/index.js` — the wizard UI, built via the existing `@wordpress/scripts` pipeline
- Pattern config: static PHP array inside `Kate_Toms_Blueprint`, one entry per page, listing pattern slugs in insertion order

### Data Model

**Post structure created per blueprint run:**

| Post | Type | Status | Parent |
|---|---|---|---|
| `{display_title}` | `houses` | `draft` | — |
| `{display_title}` | `houses` | `draft` | houses post ID |
| `{display_title} - availability - Kate and Tom's` | `houses` | `draft` | houses post ID |
| `{display_title} - book - Kate and Tom's` | `houses` | `draft` | houses post ID |
| `{display_title} - facts - Kate and Tom's` | `houses` | `draft` | houses post ID |
| `{display_title} - gallery - Kate and Tom's` | `houses` | `draft` | houses post ID |

**Post meta on parent `houses` post:**

| Meta key | Value |
|---|---|
| `crm_house_id` | CRM integer ID pulled from the API |

> **Retrospective note**: Existing block settings that reference `houseID` (e.g. House Calendar Availability block) should be migrated to read from `crm_house_id` post meta as a follow-up task. This is out of scope for the Blueprint prototype.

**Child page slugs (fixed):**

| Page | Slug |
|---|---|
| More | `more` |
| Availability | `availability` |
| Book | `book` |
| Facts | `facts` |
| Gallery | `gallery` |

### Pattern Configuration Array

Developer-maintained static array in `Kate_Toms_Blueprint`. Keys match the page identifier; values are ordered arrays of pattern slugs from the katomswold theme. To add a pattern to a page later, add its slug to the relevant array.

```php
private static array $blueprint_pages = [
    'parent' => [
        'patterns' => [
            'katomswold/house-title-banner',
            'katomswold/standard-widget-fourimage',
            'katomswold/wide-widget',
            'katomswold/houses-you-may-also-like',
        ],
    ],
    'more' => [
        'patterns' => [
            'katomswold/house-title-banner-sub-page',
            'katomswold/standard-widget-galleryright',
            'katomswold/button-widget',
        ],
    ],
    'availability' => [
        'patterns' => [
            'katomswold/house-title-banner-sub-page',
            'katomswold/button-widget',
        ],
    ],
    'book' => [
        'patterns' => [
            'katomswold/house-title-banner-sub-page',
            'katomswold/button-widget',
        ],
    ],
    'facts' => [
        'patterns' => [
            'katomswold/house-title-banner-sub-page',
            'katomswold/standard-widget-fourimage',
            'katomswold/wide-widget',
        ],
    ],
    'gallery' => [
        'patterns' => [
            'katomswold/house-title-banner-sub-page',
            'katomswold/standard-widget-galleryright',
            'katomswold/button-widget',
        ],
    ],
];
```

## Features

### Feature 1: CRM House Search (Typeahead)

**Description**: Staff type a house name into a search field; the wizard queries the CRM API and returns matching houses as a selectable list.
**User-facing**: Admin only (Blueprint page)
**Details**:
- Calls `GET /wp-json/kate-toms-core/v1/blueprint/crm-search?query={term}` (internal REST endpoint, proxied to CRM)
- Returns an array of `{ crm_id, crm_title }` objects
- Results render as a selectable list using `@wordpress/components` ComboboxControl or similar
- Selecting a result populates the CRM ID field (read-only) and the display title field (editable)

### Feature 2: Display Title Override

**Description**: The display title defaults to the CRM house name but can be freely edited before creation. This is the title used for the parent post and all child page title prefixes.
**User-facing**: Admin only
**Details**:
- Editable text input, pre-filled from CRM selection
- Used verbatim as `post_title` on the parent `houses` post
- Child page titles follow the pattern: `{display_title} - {suffix} - Kate and Tom's` (except "more" which uses display title only)

### Feature 3: Duplicate Detection

**Description**: Before creating any posts, the wizard checks whether a `houses` post with the same display title already exists.
**User-facing**: Admin only
**Details**:
- Check runs client-side via a REST call on "Create Blueprint" click, before any posts are written
- If a match is found: show an inline warning naming the existing post, offer two options — "Choose a different name" (returns to step 1) or "Override" (admin acknowledges and proceeds, creating pages with a deduplicated title suffix or forcing creation regardless — TBD at implementation)
- If no match: proceed immediately

### Feature 4: Blueprint Creation

**Description**: On confirmation, creates the parent `houses` post and all five child pages in a single server-side operation.
**User-facing**: Admin only
**Details**:
- Triggered by `POST /wp-json/kate-toms-core/v1/blueprint/create`
- Request body: `{ crm_id, display_title }`
- Server creates posts in order: parent first, then children
- `crm_house_id` meta saved on parent post
- Each post's `post_content` is assembled by loading the registered block patterns for that page and serialising them via `WP_Block_Patterns_Registry` + `serialize_blocks()`
- All posts created with `post_status: draft`
- Response returns an array of created post IDs and edit URLs

### Feature 5: Success Screen

**Description**: After successful creation, the wizard shows a confirmation screen listing all created pages with direct edit links.
**User-facing**: Admin only
**Details**:
- Lists parent post and each child page by title
- Each title is a clickable link to the WordPress block editor for that post
- "Create Another" button resets the wizard to step 1

## Admin UI

- **Menu location**: Submenu under `edit.php?post_type=houses`, label "Blueprint", capability `manage_options`
- **Wizard steps**:
  - Step 1 — Search: CRM typeahead search + display title field + "Next" button
  - Step 2 — Review: Summary of pages to be created (titles, slugs, pattern count per page) + "Create Blueprint" button
  - Step 3 — Success: Links to all created drafts + "Create Another" button
- **Inline error states**: API failures, duplicate detection warning, creation failures
- **Technology**: React via `@wordpress/components`, enqueued only on the Blueprint admin page

## Frontend Output

None. This feature is entirely admin-side. The created draft pages only appear on the frontend once a content editor publishes them manually.

## REST API

| Method | Endpoint | Auth | Description |
|---|---|---|---|
| `GET` | `/wp-json/kate-toms/v1/blueprint/crm-search` | `manage_options` | Typeahead search proxied to CRM API. Accepts `?query=` param. |
| `POST` | `/wp-json/kate-toms/v1/blueprint/create` | `manage_options` | Creates parent + child posts. Body: `{ crm_id, display_title }`. Returns created post IDs + edit URLs. |

Both endpoints are restricted to `manage_options` capability. Namespace matches existing `kate-toms/v1` convention used by `class-houses-filter-api.php`.

## Third-Party Integrations

- **Kate & Tom's CRM (iProperty Pro / 2iPro)**
  - Base URL: `https://booking.kateandtoms.com/apis/property`
  - House search endpoint: `https://booking.kateandtoms.com/apis/properties` (confirmed)
  - Auth: OAuth2 client credentials (`https://booking.kateandtoms.com/oauth/2.0/token`, Basic auth header with Base64-encoded credentials)
  - Existing implementation: `class-houses-calendar-availability-api.php` — OAuth token management and cURL request pattern to be extracted into new `Kate_Toms_Blueprint_CRM_API` class
  - The new class must handle token refresh and caching (transient-based, as per existing implementation)

## Scheduled Tasks

None.

## CLI Commands

None for prototype. Could add `wp blueprint create --crm-id=123 --title="House Name"` as a future enhancement.

## Lifecycle

- **Activation**: No changes. No tables or options added. The Blueprint submenu and REST endpoints register on every page load via existing hook system.
- **Deactivation**: No cleanup needed. Created draft posts persist; they're standard WordPress content.
- **Uninstall**: No special cleanup. Created posts are ordinary `houses` and `page` posts; they are left in place.

## Testing Strategy

- **Unit tests**: `Kate_Toms_Blueprint_CRM_API` — mock cURL responses, test OAuth token caching/refresh, test search result parsing
- **Integration tests**: Blueprint creation endpoint — assert correct number of posts created, correct meta saved, correct post_parent relationships, correct post_status
- **Integration tests**: Duplicate detection — assert warning fires when a matching `houses` title exists
- **Manual/e2e**: Playwright test against Valet site — full wizard flow from search to success screen; verify edit links resolve

## Open Questions

1. ~~**CRM search endpoint**~~ — resolved: `https://booking.kateandtoms.com/apis/properties`
2. **Duplicate override behaviour**: When staff acknowledge a duplicate and choose to proceed, should the wizard append a suffix (e.g. `- Copy`) to the display title, or create the post with the exact same title and let WordPress handle the slug deduplication? Defer to implementation.
3. ~~**Child page post type**~~ — resolved: all child posts use `houses` post type with `post_parent` set to the parent houses post ID.
4. **Pattern injection method**: Confirm that `WP_Block_Patterns_Registry::get_instance()->get_registered( $slug )` is available in this WordPress version and returns serialisable block content, or whether patterns need to be loaded directly from the theme PHP files.
5. **`houseID` migration**: Blocks currently reference `houseID` as a block attribute/setting rather than reading post meta. The migration to `crm_house_id` post meta is explicitly out of scope for this prototype but should be tracked as a follow-up task.
