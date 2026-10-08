# ACF / legacy widget meta: retention audit (BugHerd #423, Phase 0)

Audited 2026-10-07 against the local DB (blog 1) and the live code: `kate-toms-core`,
`katomswold`, active owned plugins (`kate-and-toms-get-in-touch`,
`kate-and-toms-blog-rewrite-rule`) and `mu-plugins`. `houses-filter` and
`kate-and-toms-wati` are **inactive** and were excluded. **ACF itself is not active**,
so nothing calls `get_field()` and no `_key` reference row is read at runtime.

Machine-readable result: `includes/cleanup/retained-keys.php`.

## 1. Where the bytes are

`wp_postmeta` is 3.0 GB / ~13M rows. By owning post type:

| post_type | rows | value MB |
|---|---:|---:|
| **revision** | **12,260,881** | **508** |
| houses | 584,040 | 48 |
| attachment | 195,595 | 239 (core `_wp_attachment_metadata`, out of scope) |
| everything else | ~65k | ~3 |
| orphaned (post gone) | 4,191 | 0.4 |

Almost all legacy meta sits on revisions, because ACF copied every field onto every
revision. Only 71 revision rows aren't legacy. No meta is registered with
`revisions_enabled`, so WordPress never reads revision meta back.

`wp_termmeta` legacy ≈ 40 KB (129 terms). `wp_options` has no legacy ACF bloat.

## 2. Retained keys (live reads)

| key | stored | read by (file:line) | attachment IDs | `_key` row |
|---|---|---|---|---|
| `sleeps_min`, `sleeps_max` | postmeta (houses) | core `includes/class-custom-block-bindings.php:75`, `includes/houses-filter/class-houses-filter-api.php:349` (SQL), `blocks/*/render.php`, theme `parts/card-house.php:33`, block bindings in 5 patterns | no | exists (19,966); keep |
| `location_text` | postmeta (houses) | `class-custom-block-bindings.php:168`, `class-kate-toms-house-schema.php:501`, bindings in 11 patterns | no | exists; keep |
| `brief_description` | postmeta (houses) | `class-autocomplete-search-api.php:103`, `class-kate-toms-house-schema.php:442`, bindings in 11 patterns | no | exists; keep |
| `all_prices_with_from` | postmeta (houses) | `class-houses-filter-api.php:969`, `blocks/house-*-landing-pages/render.php` | no | exists; keep |
| `house_photos` | postmeta (houses) | `class-kate-toms-house-schema.php:465` | **yes** (serialised array) | exists (19,964); keep |
| `ipro_property_id` | postmeta (houses) | `class-houses-calendar-availability-api.php:1873,2103`, `blocks/house-booking-flow/render.php:43`, get-in-touch `public/class-kate-and-toms-get-in-touch-public.php:294`; registered `class-kate-toms-blueprint.php:223` | no | none (not ACF) |
| `_signature_collection_enabled` | postmeta (houses) | `admin/class-kate-toms-core-admin.php:1184`, `admin/js/houses-meta-sidebar.js:46` | no | n/a |
| `periods_to_include` | postmeta (seasonal, availability) | `blocks/house-*-landing-pages/render.php`, `class-seasonal-cache-warmer.php:171`; registered admin:847/891 | no | exists (25); keep |
| `rolling_upcoming_period` | postmeta (availability) | `blocks/house-availability-landing-pages/render.php:25`, `class-seasonal-cache-warmer.php:178` | no | exists; keep |
| `beginning`, `ending` | postmeta (seasonal) | `blocks/house-seasonal-landing-pages/render.php:29`, `class-seasonal-cache-warmer.php:187` | no | exists; keep |
| `availability_calendar`, `availability_calendar_N_month`, `availability_calendar_N_availability-days` | postmeta (houses) | **`class-houses-filter-api.php:187`** `get_matching_post_ids()` (LIKE `%_availability-days` / `%_month`), called from `:371` on every `/houses` date search | no | exists; keep |
| `_kt_vr_tour_backup` | postmeta (houses sub-pages) | migration `apply/remove_house_vr_tour` (:6473, :6571) | no (block markup) | n/a |
| `signature_collection_badge_id` | option | `admin/class-kate-toms-core-admin.php:1059,1190` | **yes** | n/a |
| `options_adverts`, `options_adverts_N_advert_image`, `options_adverts_N_location` | options | `admin/class-kate-toms-core-admin.php:1373–1577` | **yes** (`_advert_image`) | none locally |

Not confirmed from your list:
- **`town` and `country` aren't meta keys.** They're taxonomy slugs and pattern-style names, with zero rows in postmeta or termmeta. Nothing needs retaining for them.
- `ipro_property_id` has no `_key` row; it isn't ACF-shaped.

The `metaKey` block attribute (landing-page blocks) defaults to `sleeps_max`, and no saved content overrides it.

## 3. Migration-source keys (not live; decision needed)

Read only by `block-editor-content-migration` `wp eval` actions. No page render or
REST call reads them. They're retained in `retained-keys.php` for now.

| key | read by (migration plugin line) | attachment IDs | live-post rows |
|---|---|---|---:|
| `keyfacts_1..3` | `append_key_facts_to_house_pages` :5359 | no | ~4.4k (93 MB incl. revisions) |
| `related_houses` | :1097, :2693, :4368, :4808, :5252 | no (post IDs) | — |
| `location` (ACF map) | :5167 | no | — |
| `title_textarea`, `title_image`, `title_color`, `image_rotator` | header banners :1602–1606, :710 | **yes** (`title_image`, `image_rotator`) | — |
| `special_offer`, `show_associated_houses` | :205, :2764 | no | — |
| `ah_from_*`, `ah_section_*` | `$$ah_from_size$$` placeholders in theme patterns | no | ~350 |
| `house_photos` | :555, :835, :881, :1009, :4774 | yes | (already retained as live) |

## 4. Proposed removal set

Matched against the key with one leading underscore stripped, so every rule also
removes its ACF `_key` row. Every candidate is checked against the retained guard.

| group | rule (anchored regex) | revision rows | live rows | orphan | MB |
|---|---|---:|---:|---:|---:|
| A widgets repeaters | `^(widgets\|lower_widgets\|top_widgets\|bottom_widgets\|kf_widgets)(_.*)?$` | 6,319,457 | 249,071 | 814 | 472 |
| B availability rates | `^availability_calendar_\d+_(rates\|rate_types)(_.*)?$` | 3,220,843 | 178,343 | 1,737 | 184 |
| C price details | `^price_details(_extra)?(_.*)?$` | 1,478,403 | 79,672 | 743 | 84 |
| D legacy scalars (exact) | `availability_general_text, availability_option, brief_description_winter, house_currency, turn_off_take_a_tour, limit_num_photos, discount_logic, discount_logic_layout_meta, sub_text, headline_text, house_thumbnails, availability_site_post_id, availability_site_ref, offer_repeater, home_page_text, supplier_photos, external_name` | 380,822 | 15,489 | 244 | 20 |
| **total** | | **11.4M** | **522k** | 3.5k | **~760** |

Termmeta (all of it is legacy, from `register_fields_taxonomies.php` and the term top/bottom widgets):
`^(widgets|top_widgets|bottom_widgets)(_.*)?$` and the exact keys `taxonomy_textarea, taxonomy_filter_layout_option, taxonomy_intro_image, tax_banner_color, tax_color_scheme, tax_filter_button_color_scheme, tax_intro_image, house_for_override, include_in_search_filter, min_no, max_no` (+ `_` rows). None of these is read anywhere live.

Options: **nothing**.

Never touched: WordPress core / Yoast / Jetpack / Smush / YouTube-feed / cookie-law keys
(`_wp_*`, `_edit_*`, `_yoast_*`, `_menu_item_*`, `_oembed_*`, `_thumbnail_id`,
`sby_*`, `_jetpack_*`, `CLI*`, `footnotes`, `core-color`, etc.).

## 5. Overlap list

1. **`availability_calendar` repeater.** Its rates/rate_types rows are legacy, but `month`, `availability-days` and the count row are read live by the `/houses` date pre-filter (`class-houses-filter-api.php:187`). Rule B is scoped to rates/rate_types only, and the guard protects the rest as well. The data is stale (it predates the migration, and live availability now comes from the `kt_house_calendar_*` cache at `:425`). Suggested follow-up: drop the pre-filter in a small PR, then remove these 434k rows (30 MB).
2. **The prompt's `widget\_%` / `\_widget%` patterns are wrong for this data.**
   - `widget\_%` matches **none** of the `widgets_N_*` rows (the keys are plural).
   - Neither pattern matches `lower_widgets`, `top_widgets`, `bottom_widgets` or `kf_widgets`.
   - In `wp_options`, `widget_%` is **WordPress core's widget storage** (`widget_block`, `widget_text`…).

   Rule A replaces them, and the command never touches options.
3. **Prefix collisions.** Prefix-style LIKE rules would delete live keys:
   - `brief_description%` would hit `brief_description_winter` and `brief_description`
   - `location%` would hit `location_text`
   - `availability_%` would hit the live calendar keys

   All rules are anchored regexes or exact keys, and the SQL uses exact `IN (...)` lists built from them.
4. **ACF-shaped but live.** These look like ACF data but are retained:
   - `sleeps_min/max`, `location_text`, `brief_description`, `all_prices_with_from`, `house_photos`
   - `periods_to_include`, `rolling_upcoming_period` (defined in clubsandwich `register_fields_availability.php:19-20`), `beginning`, `ending`

   Their `_key` rows are kept too. Nothing reads them, but they're tiny, and the media audit uses them to recognise ACF shape.
5. **Images losing their only reference.** Group A holds attachment IDs: `kf_widgets_N_floorplan_image` (~20k rows), `widgets_N_gallery`, `widgets_N_wide_image`, `widgets_N_review_image`, `*_imageset_N_row_N_image`, `*_matrix_set_N_image` and `widgets_N_virtual_image`. Once they're gone, the media audit will move any image that isn't in block content to "Unused". That's the intended step 4, but **floorplans** deserve a look before deletion.
6. **Retained keys on revisions.** About 639k revision rows carry retained keys (230,759 house fields plus 408,028 availability_calendar month/days). Nothing reads them (see §1).

## 6. Decisions (agreed 2026-10-07)

1. **Migration-source keys (§3)** are kept on live posts and removed from revisions.
2. **Revision scope:** the retained guard protects live posts and terms only. Revision copies of retained keys are deleted (`KT_Cleanup_Key_Rules::decide()`).
3. **Revision posts** (53,399 rows, `wp_posts` 1.3 GB) are a separate task, outside #423.
4. **`availability_calendar` month/days:** retired. Their last reader, the `/houses` date pre-filter, was removed in #73, so the whole repeater is now legacy group B in `legacy-keys.php`.

## 7. Local dry run (2026-10-08, existing local DB, after #73)

| table | would delete (live) | would delete (revision) | orphans (not deleted) | retained kept | meta_value MB |
|---|---:|---:|---:|---:|---:|
| wp_postmeta | 548,726 | 12,260,810 | 4,014 | 0 | 540.3 |
| wp_termmeta | 2,806 | 0 | 72 | 0 | 0.0 |

These numbers come from the current local DB and are a baseline only. The go/no-go check is a rehearsal on a fresh production copy, whose numbers must match the production dry run exactly.
