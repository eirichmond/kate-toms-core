<?php
/**
 * Meta keys and options the live stack still reads (BugHerd #423).
 *
 * Single source of truth for the legacy meta cleanup (`wp kt cleanup legacy-meta`)
 * and the media audit mu-plugin. Anything matched here must never be deleted,
 * even when it also matches a legacy widget/ACF pattern, and is treated as a
 * live reference by the media audit.
 *
 * Matching rule: a key is retained if it, or the key with ONE leading
 * underscore removed (the ACF `_key` => `field_xxx` reference row), matches an
 * `exact` entry or a `patterns` regex. Keys that genuinely start with an
 * underscore (e.g. `_signature_collection_enabled`) are listed as-is.
 *
 * Evidence (file:line for every key) lives in docs/acf-cleanup/retention-audit.md.
 *
 * @package Kate_Toms_Core
 */

defined( 'ABSPATH' ) || exit;

return array(

	// Read by kate-toms-core, katomswold or kate-and-toms-get-in-touch at runtime.
	'exact'                     => array(
		// House card / details fields (postmeta, houses).
		'sleeps_min',
		'sleeps_max',
		'location_text',
		'brief_description',
		'all_prices_with_from',
		'house_photos',
		'ipro_property_id',
		'_signature_collection_enabled',
		// Seasonal + availability landing pages (postmeta, seasonal / availability).
		'periods_to_include',
		'rolling_upcoming_period',
		'beginning',
		'ending',
		// Repeater count row for the date filter below.
		'availability_calendar',
		// VR tour retrofit backup (apply/remove_house_vr_tour).
		'_kt_vr_tour_backup',
		// Options.
		'signature_collection_badge_id',
		'options_adverts',
	),

	// Dynamic keys. Anchored, delimiter `/`.
	'patterns'                  => array(
		// Houses filter date pre-filter: class-houses-filter-api.php get_matching_post_ids().
		'/^availability_calendar_\d+_(month|availability-days)$/',
		// Adverts repeater: admin/class-kate-toms-core-admin.php.
		'/^options_adverts_\d+_(advert_image|location)$/',
	),

	// Only read by block-editor-content-migration `wp eval` retrofits, not by
	// any page render. Retained by default until a decision is made (see
	// retention-audit.md, "Decisions needed"). Merged into the guard.
	'migration_source'          => array(
		'keyfacts_1',
		'keyfacts_2',
		'keyfacts_3',
		'location',
		'related_houses',
		'title_textarea',
		'title_image',
		'title_color',
		'image_rotator',
		'special_offer',
		'show_associated_houses',
	),

	// Pattern placeholders ($$ah_from_size$$ etc.) filled by the migration.
	'migration_source_patterns' => array(
		'/^ah_(from|section)_[a-z_]+$/',
	),

	// Retained keys whose values are attachment IDs (single ID or serialised
	// array). The media audit treats these as live references (status `used`).
	'attachment_keys'           => array(
		'exact'    => array(
			'house_photos',
			'image_rotator',
			'title_image',
			'signature_collection_badge_id',
		),
		'patterns' => array(
			'/^options_adverts_\d+_advert_image$/',
		),
	),
);
