<?php
/**
 * Server-side rendering for the House Filter Breadcrumb block.
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Block default content.
 * @var WP_Block $block      Block instance.
 */

// Build label maps from taxonomy terms.
$locations = get_terms(
	array(
		'taxonomy'   => 'location',
		'hide_empty' => true,
	)
);

$features = get_terms(
	array(
		'taxonomy'   => 'feature',
		'hide_empty' => true,
	)
);

$location_label_map = array();
$location_mappings  = array(
	'In the Cotswolds' => 'Cotswolds',
	'By the Coast'     => 'Coast',
	'In the Country'   => 'Country',
);

if ( ! is_wp_error( $locations ) ) {
	foreach ( $locations as $loc ) {
		$label = $location_mappings[ $loc->name ] ?? $loc->name;
		$location_label_map[ (string) $loc->term_id ] = $label;
	}
}

$feature_label_map = array();
$feature_mappings  = array(
	'Pool'           => 'Pool',
	'With a Hot Tub' => 'Hot Tub',
	'Beach'          => 'Beach',
);

if ( ! is_wp_error( $features ) ) {
	foreach ( $features as $feat ) {
		$label = $feature_mappings[ $feat->name ] ?? $feat->name;
		$feature_label_map[ (string) $feat->term_id ] = $label;
	}
}

$size_label_map = array(
	'2-10' => 'Sleeps 2-10',
	'10-20' => 'Sleeps 10-20',
	'20+'  => 'Sleeps 20+',
);

// Inject label maps into the shared store.
wp_interactivity_state(
	'kate-toms-house-filter',
	array(
		'sizeLabelMap'    => $size_label_map,
		'localLabelMap'   => $location_label_map,
		'featureLabelMap' => $feature_label_map,
	)
);

?>

<div
<?php
	echo wp_kses_post(
		get_block_wrapper_attributes(
			array(
				'class' => 'house-filter-breadcrumb',
			)
		)
	);
?>
	data-wp-interactive="kate-toms-house-filter"
	data-wp-bind--hidden="!state.hasBreadcrumbs">

	<!-- Size breadcrumb -->
	<span class="house-filter-breadcrumb__item"
		data-wp-bind--hidden="!state.activeSizeLabel">
		<svg class="house-filter-breadcrumb__icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16" aria-hidden="true">
			<path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z"/>
		</svg>
		<span data-wp-text="state.activeSizeLabel"></span>
	</span>

	<!-- Location breadcrumb -->
	<span class="house-filter-breadcrumb__item"
		data-wp-bind--hidden="!state.activeLocalLabel">
		<svg class="house-filter-breadcrumb__icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16" aria-hidden="true">
			<path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/>
		</svg>
		<span data-wp-text="state.activeLocalLabel"></span>
	</span>

	<!-- Feature breadcrumb -->
	<span class="house-filter-breadcrumb__item"
		data-wp-bind--hidden="!state.activeFeatureLabel">
		<svg class="house-filter-breadcrumb__icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16" aria-hidden="true">
			<path d="M21.41 11.58l-9-9C12.05 2.22 11.55 2 11 2H4c-1.1 0-2 .9-2 2v7c0 .55.22 1.05.59 1.42l9 9c.36.36.86.58 1.41.58.55 0 1.05-.22 1.41-.59l7-7c.37-.36.59-.86.59-1.41 0-.55-.23-1.06-.59-1.42zM5.5 7C4.67 7 4 6.33 4 5.5S4.67 4 5.5 4 7 4.67 7 5.5 6.33 7 5.5 7z"/>
		</svg>
		<span data-wp-text="state.activeFeatureLabel"></span>
	</span>

	<!-- Reset filters (visible whenever a size, location or feature is active) -->
	<button type="button" class="house-filter-breadcrumb__reset" data-wp-on--click="actions.resetFilters">
		<svg class="house-filter-breadcrumb__reset-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1920 1920" width="16" height="16" aria-hidden="true" focusable="false">
			<path d="M960 0v213.333c411.627 0 746.667 334.934 746.667 746.667S1371.627 1706.667 960 1706.667 213.333 1371.733 213.333 960c0-197.013 78.4-382.507 213.334-520.747v254.08H640V106.667H53.333V320h191.04C88.64 494.08 0 720.96 0 960c0 529.28 430.613 960 960 960s960-430.72 960-960S1489.387 0 960 0" fill-rule="evenodd"/>
		</svg>
		<span><?php esc_html_e( 'Clear filters', 'kate-toms-core' ); ?></span>
	</button>
</div>
