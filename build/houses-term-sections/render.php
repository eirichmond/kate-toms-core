<?php
/**
 * Houses Term Sections Block Template.
 *
 * Renders the four location "region" sections (Cotswolds, Coast, Country, Town)
 * on a taxonomy archive, each constrained to houses tagged with BOTH the region
 * and the currently archived term. Regions with no matching houses are skipped
 * entirely, so a term that only has houses in (say) the Country won't show empty
 * Cotswolds / Coast / Town sections.
 *
 * The actual house grid + infinite scroll is delegated to the
 * kate-toms-core/houses-filtered-results block, which is told about the archived
 * term via its contextTaxonomy / contextTerm attributes.
 *
 * @package kate-toms-core
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Block default content.
 * @var WP_Block $block      Block instance.
 */

// This block only makes sense on a taxonomy archive.
$queried = get_queried_object();
if ( ! ( $queried instanceof WP_Term ) ) {
	return;
}

$context_taxonomy = $queried->taxonomy;
$context_term     = (int) $queried->term_id;

$regions = function_exists( 'kate_toms_core_get_region_sections' )
	? kate_toms_core_get_region_sections()
	: array();

if ( empty( $regions ) ) {
	return;
}

// The `.houses-grid` / `.house-card` layout lives in the houses-filter block's
// stylesheet (it is rendered by houses-filtered-results but styled globally
// there). That block isn't present on taxonomy archives, so enqueue its style
// explicitly — otherwise the cards stack full-width instead of forming a grid.
if ( wp_style_is( 'kate-toms-core-houses-filter-style', 'registered' ) ) {
	wp_enqueue_style( 'kate-toms-core-houses-filter-style' );
}

$wrapper_attributes = get_block_wrapper_attributes();
$sections_markup    = '';

// Per-section block markup. Rendered through do_blocks() (rather than emitted as
// plain HTML) so WordPress generates the constrained-layout container CSS — that
// is what makes the inner "alignwide" group actually constrain the house grid to
// the wide content width while the coloured section group stays full width.
$section_template = <<<'HTML'
<!-- wp:group {"align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|40","bottom":"var:preset|spacing|40","left":"var:preset|spacing|40","right":"var:preset|spacing|40"}}},"backgroundColor":"%1$s","layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull has-%1$s-background-color has-background" style="padding-top:var(--wp--preset--spacing--40);padding-right:var(--wp--preset--spacing--40);padding-bottom:var(--wp--preset--spacing--40);padding-left:var(--wp--preset--spacing--40)"><!-- wp:group {"align":"wide","layout":{"type":"default"}} -->
<div class="wp-block-group alignwide"><!-- wp:heading {"textAlign":"center","style":{"typography":{"fontStyle":"normal","fontWeight":"300"},"spacing":{"padding":{"top":"var:preset|spacing|40","bottom":"var:preset|spacing|40"}}},"fontSize":"x-large"} -->
<h2 class="wp-block-heading has-text-align-center has-x-large-font-size" style="padding-top:var(--wp--preset--spacing--40);padding-bottom:var(--wp--preset--spacing--40);font-style:normal;font-weight:300">%2$s</h2>
<!-- /wp:heading -->

%3$s</div>
<!-- /wp:group --></div>
<!-- /wp:group -->
HTML;

foreach ( $regions as $region ) {
	$region_term_id = (int) $region['term_id'];

	// Cheap existence check: does this region have at least one house that also
	// carries the archived term? Mirror the houses-filtered-results query
	// constraints (published + sleeps_max EXISTS) so we never show a section
	// that would then render "No houses found".
	$tax_query = array(
		'relation' => 'AND',
		array(
			'taxonomy' => 'location',
			'field'    => 'term_id',
			'terms'    => $region_term_id,
		),
		array(
			'taxonomy' => $context_taxonomy,
			'field'    => 'term_id',
			'terms'    => $context_term,
		),
	);

	$count_query = new WP_Query(
		array(
			'post_type'              => 'houses',
			'post_status'            => 'publish',
			'posts_per_page'         => 1,
			'fields'                 => 'ids',
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
			'tax_query'              => $tax_query,
			'meta_query'             => array(
				array(
					'key'     => 'sleeps_max',
					'compare' => 'EXISTS',
					'type'    => 'NUMERIC',
				),
			),
		)
	);

	if ( ! $count_query->have_posts() ) {
		continue;
	}

	$background = sanitize_html_class( $region['background'] );

	// The inner houses-filtered-results block, passing the region plus the
	// archived term so it filters (and infinite-scrolls) on both.
	$inner_attrs = wp_json_encode(
		array(
			'defaultLocation' => $region_term_id,
			'contextTaxonomy' => $context_taxonomy,
			'contextTerm'     => $context_term,
		)
	);
	$inner_block = '<!-- wp:kate-toms-core/houses-filtered-results ' . $inner_attrs . ' /-->';

	$sections_markup .= sprintf(
		$section_template,
		$background,
		esc_html( $region['heading'] ),
		$inner_block
	);
}

if ( '' === $sections_markup ) {
	return;
}
?>
<div <?php echo wp_kses_post( $wrapper_attributes ); ?>>
	<?php echo do_blocks( $sections_markup ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- do_blocks output. ?>
</div>
