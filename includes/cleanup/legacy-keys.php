<?php
/**
 * Legacy clubsandwich / ACF meta keys removed by `wp kt cleanup legacy-meta` (BugHerd #423).
 *
 * Same matching rule as retained-keys.php: a key matches if it, or the key
 * with ONE leading underscore removed (its ACF `_key` reference row), matches
 * an `exact` entry or a `patterns` regex. Patterns must be anchored with `^`
 * and start with a literal prefix, which becomes the SQL LIKE pre-filter.
 *
 * Retained keys always win: see KT_Cleanup_Key_Rules::decide().
 * Groups and sizes are documented in docs/acf-cleanup/retention-audit.md §4.
 *
 * @package Kate_Toms_Core
 */

defined( 'ABSPATH' ) || exit;

return array(

	'postmeta' => array(
		'exact'    => array(
			// Group D: legacy scalar house / page fields.
			'availability_general_text',
			'availability_option',
			'brief_description_winter',
			'house_currency',
			'turn_off_take_a_tour',
			'limit_num_photos',
			'discount_logic',
			'discount_logic_layout_meta',
			'sub_text',
			'headline_text',
			'house_thumbnails',
			'availability_site_post_id',
			'availability_site_ref',
			'offer_repeater',
			'home_page_text',
			'supplier_photos',
			'external_name',
		),
		'patterns' => array(
			// Group A: Widget Factory flexible-content repeaters.
			'/^widgets(_.*)?$/',
			'/^lower_widgets(_.*)?$/',
			'/^top_widgets(_.*)?$/',
			'/^bottom_widgets(_.*)?$/',
			'/^kf_widgets(_.*)?$/',
			// Group B: legacy availability rates (month / availability-days are retained).
			'/^availability_calendar_\d+_(rates|rate_types)(_.*)?$/',
			// Group C: legacy price tables.
			'/^price_details(_.*)?$/',
		),
	),

	'termmeta' => array(
		'exact'    => array(
			'taxonomy_textarea',
			'taxonomy_filter_layout_option',
			'taxonomy_intro_image',
			'tax_banner_color',
			'tax_color_scheme',
			'tax_filter_button_color_scheme',
			'tax_intro_image',
			'house_for_override',
			'include_in_search_filter',
			'min_no',
			'max_no',
		),
		'patterns' => array(
			'/^widgets(_.*)?$/',
			'/^top_widgets(_.*)?$/',
			'/^bottom_widgets(_.*)?$/',
		),
	),
);
