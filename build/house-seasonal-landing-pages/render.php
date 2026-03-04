<?php
/**
 * House Seasonal Landing Pages Block Template.
 *
 * Filters houses based on the current seasonal post's criteria:
 * - beginning and ending dates
 * - periods_to_include (weeks, weekends, midweeks, etc.)
 *
 * Uses Interactivity API for infinite scroll pagination.
 * All houses are filtered on initial load (availability check cannot be paginated),
 * then only the first page is rendered. Subsequent pages load via REST using pre-filtered IDs.
 *
 * @param array    $attributes Block attributes.
 * @param string   $content    Block default content.
 * @param WP_Block $block      Block instance.
 */

// Get the current post (should be a seasonal post).
$current_post_id   = get_the_ID();
$current_post_type = get_post_type( $current_post_id );

// Only proceed if we're on a seasonal post.
if ( $current_post_type !== 'seasonal' ) {
	echo '<p>' . __( 'This block only works on Seasonal post types.', 'kate-toms-core' ) . '</p>';
	return;
}

// Get seasonal criteria from current post meta.
$beginning_date     = get_post_meta( $current_post_id, 'beginning', true );
$ending_date        = get_post_meta( $current_post_id, 'ending', true );
$periods_to_include = get_post_meta( $current_post_id, 'periods_to_include', true );

// Validate we have the required meta.
if ( empty( $beginning_date ) || empty( $ending_date ) || empty( $periods_to_include ) ) {
	echo '<p>' . __( 'Please configure the seasonal offer settings (beginning date, ending date, and periods) in the sidebar.', 'kate-toms-core' ) . '</p>';
	return;
}

// Map seasonal period names to API period keys.
$period_mapping = array(
	'Week'            => 'week',
	'weeks'           => 'week',
	'week'            => 'week',
	'2 night weekend' => '2-night-weekend',
	'3 night weekend' => '3-night-weekend',
	'Midweek'         => 'midweek',
	'5 night'         => 'week',
);

// Convert periods to API format.
$api_periods = array();
foreach ( $periods_to_include as $period ) {
	if ( isset( $period_mapping[ $period ] ) ) {
		$api_periods[] = $period_mapping[ $period ];
	}
}

if ( empty( $api_periods ) ) {
	echo '<p>' . __( 'No valid periods configured.', 'kate-toms-core' ) . '</p>';
	return;
}

// Get block attributes.
$posts_per_page = $attributes['postsPerPage'] ?? 20;
$order_by       = $attributes['orderBy'] ?? 'meta_value_num';
$order          = $attributes['order'] ?? 'desc';
$meta_key       = $attributes['metaKey'] ?? 'sleeps_max';
$pattern_style  = $attributes['patternStyle'] ?? 'coast';

// Map pattern styles to background colors and location taxonomy slugs.
$pattern_config = array(
	'coast'     => array(
		'color'         => 'coloreight',
		'taxonomy_slug' => 'sea',
		'name'          => 'By the Coast',
	),
	'cotswolds' => array(
		'color'         => 'colorfive',
		'taxonomy_slug' => 'cotswolds',
		'name'          => 'In the Cotswolds',
	),
	'country'   => array(
		'color'         => 'titlecolorthree',
		'taxonomy_slug' => 'country',
		'name'          => 'In the Country',
	),
	'town'      => array(
		'color'         => 'coloreight',
		'taxonomy_slug' => 'town',
		'name'          => 'In town',
	),
);

$pattern_data   = $pattern_config[ $pattern_style ] ?? $pattern_config['coast'];
$title_bg_color = $pattern_data['color'];
$location_slug  = $pattern_data['taxonomy_slug'];
$location_name  = $pattern_data['name'];

// Build the query arguments — fetch ALL houses for availability filtering.
$query_args = array(
	'post_type'      => 'houses',
	'post_status'    => 'publish',
	'posts_per_page' => -1,
	'orderby'        => $order_by,
	'order'          => $order,
	'tax_query'      => array(
		array(
			'taxonomy' => 'location',
			'field'    => 'slug',
			'terms'    => $location_slug,
		),
	),
);

// Add meta query and meta_key for meta_value_num ordering.
if ( $order_by === 'meta_value_num' ) {
	$query_args['meta_key']   = $meta_key;
	$query_args['meta_query'] = array(
		array(
			'key'     => $meta_key,
			'compare' => 'EXISTS',
			'type'    => 'NUMERIC',
		),
	);
}

// Execute the query to get all houses (we'll filter by availability after).
$houses_query = new WP_Query( $query_args );

if ( ! $houses_query->have_posts() ) {
	echo '<p>' . __( 'No houses found.', 'kate-toms-core' ) . '</p>';
	return;
}

// Filter houses by seasonal availability.
$filtered_houses = kate_toms_filter_houses_by_seasonal_availability(
	$houses_query->posts,
	$beginning_date,
	$ending_date,
	$api_periods
);

if ( empty( $filtered_houses ) ) {
	echo '<p>' . __( 'No houses available for the selected dates and periods.', 'kate-toms-core' ) . '</p>';
	echo '<p><small>Checked ' . count( $houses_query->posts ) . ' houses for availability between ' . esc_html( $beginning_date ) . ' and ' . esc_html( $ending_date ) . ' for periods: ' . esc_html( implode( ', ', $api_periods ) ) . '</small></p>';
	return;
}

// Collect all filtered house IDs (preserving order).
$all_house_ids = array_map(
	function ( $house ) {
		return $house->ID;
	},
	$filtered_houses
);

// Paginate: slice first page for initial render.
$total_houses  = count( $filtered_houses );
$total_pages   = ( $posts_per_page > 0 ) ? (int) ceil( $total_houses / $posts_per_page ) : 1;
$initial_houses = ( $posts_per_page > 0 ) ? array_slice( $filtered_houses, 0, $posts_per_page ) : $filtered_houses;
$has_more      = $total_pages > 1;

// Calculate adverts — only show when single page (all houses visible).
$initial_adverts = array();
if ( ! $has_more ) {
	$remainder = $total_houses % 4;
	if ( $remainder > 0 ) {
		$adverts_needed = 4 - $remainder;
		if ( class_exists( 'Kate_Toms_Core_Admin' ) ) {
			$admin           = new Kate_Toms_Core_Admin( 'kate-toms-core', '1.0.0' );
			$initial_adverts = $admin->get_adverts_for_location( $pattern_style, $adverts_needed );
		}
	}
}

// Set up Interactivity API context.
$context = array(
	'allHouseIds'   => $all_house_ids,
	'postsPerPage'  => $posts_per_page,
	'currentPage'   => 1,
	'totalPages'    => $total_pages,
	'totalHouses'   => $total_houses,
	'isLoading'     => false,
	'hasMore'       => $has_more,
	'titleBgColor'  => $title_bg_color,
	'patternStyle'  => $pattern_style,
	'locationKey'   => $pattern_style,
	'beginningDate' => $beginning_date,
	'endingDate'    => $ending_date,
);

// All period keys for seasonal pricing display.
$all_period_keys = array( 'week', '2-night-weekend', '3-night-weekend', 'midweek' );

?>

<div
	<?php echo wp_kses_post( get_block_wrapper_attributes( array( 'class' => 'house-seasonal-landing-pages' ) ) ); ?>
	data-wp-interactive="kate-toms-house-seasonal-landing-pages"
	data-wp-context='<?php echo wp_json_encode( $context ); ?>'
>
	<div class="house-seasonal-grid" data-wp-class--is-loading="context.isLoading">
		<style>
		.house-seasonal-grid {
			display: grid;
			grid-template-columns: repeat(4, 1fr);
			gap: 20px;
		}
		.house-seasonal-results,
		.house-seasonal-adverts,
		.house-seasonal-skeletons {
			display: contents;
		}
		@media (max-width: 1200px) {
			.house-seasonal-grid { grid-template-columns: repeat(3, 1fr); }
		}
		@media (max-width: 900px) {
			.house-seasonal-grid { grid-template-columns: repeat(2, 1fr); }
		}
		@media (max-width: 600px) {
			.house-seasonal-grid { grid-template-columns: 1fr; }
		}
		</style>

		<div class="house-seasonal-results">
			<?php foreach ( $initial_houses as $house ) : ?>
				<!-- House Card -->
				<div class="wp-block-group has-white-background-color has-background house-card" style="min-height:365px">
					<!-- Featured Image -->
					<?php if ( has_post_thumbnail( $house->ID ) ) : ?>
						<a href="<?php echo esc_url( get_permalink( $house->ID ) ); ?>">
							<?php echo get_the_post_thumbnail( $house->ID, 'house_search', array( 'style' => 'width: 100%; height: auto; display: block;' ) ); ?>
						</a>
					<?php endif; ?>

					<!-- Post Title with styling from pattern -->
					<h2 class="wp-block-heading has-text-align-center has-small-font-size has-white-color has-<?php echo esc_attr( $title_bg_color ); ?>-background-color"
					   style="margin-top:0;margin-bottom:0;padding-top:var(--wp--preset--spacing--40);padding-right:var(--wp--preset--spacing--40);padding-bottom:var(--wp--preset--spacing--40);padding-left:var(--wp--preset--spacing--40);font-style:normal;font-weight:600;font-size:var(--wp--preset--font-size--small)">
						<a href="<?php echo esc_url( get_permalink( $house->ID ) ); ?>" style="color: var(--wp--preset--color--white); text-decoration: none;">
							<?php echo esc_html( get_the_title( $house->ID ) ); ?>
						</a>
					</h2>

					<!-- Brief Description -->
					<div class="wp-block-group" style="padding-top:var(--wp--preset--spacing--30);padding-right:var(--wp--preset--spacing--30);padding-bottom:var(--wp--preset--spacing--30);padding-left:var(--wp--preset--spacing--30)">
						<?php
						$brief_description = get_post_meta( $house->ID, 'brief_description', true );
						if ( $brief_description ) :
							?>
							<p class="has-x-small-font-size"><?php echo esc_html( $brief_description ); ?></p>
						<?php endif; ?>
					</div>

					<!-- Sleeps and Location Info -->
					<div class="wp-block-group" style="border-top-color:var(--wp--preset--color--tertiary);border-top-width:1px;padding-top:var(--wp--preset--spacing--30);padding-right:var(--wp--preset--spacing--30);padding-bottom:var(--wp--preset--spacing--30);padding-left:var(--wp--preset--spacing--30)">
						<div class="wp-block-group" style="display: flex; justify-content: space-between; align-items: center;">
							<!-- Sleeps Info -->
							<div class="wp-block-group" style="display: flex; align-items: center; gap: 0.2em;">
								<?php
								$sleeps_min = get_post_meta( $house->ID, 'sleeps_min', true );
								$sleeps_max = get_post_meta( $house->ID, 'sleeps_max', true );
								if ( $sleeps_max ) :
									?>
									<p class="has-x-small-font-size" style="margin: 0;">Sleeps </p>
									<?php if ( $sleeps_min ) : ?>
										<p class="has-x-small-font-size" style="margin: 0;"><?php echo esc_html( $sleeps_min ); ?></p>
										<p class="has-x-small-font-size" style="margin: 0;"> to </p>
									<?php endif; ?>
									<p class="has-x-small-font-size" style="margin: 0;"><?php echo esc_html( $sleeps_max ); ?></p>
								<?php endif; ?>
							</div>

							<!-- Location Info -->
							<div class="wp-block-group">
								<?php
								$location_text = get_post_meta( $house->ID, 'location_text', true );
								if ( $location_text ) :
									?>
									<p class="has-text-align-right has-x-small-font-size" style="margin: 0;"><?php echo esc_html( $location_text ); ?></p>
								<?php endif; ?>
							</div>
						</div>
					</div>

					<!-- Seasonal Prices -->
					<?php
					$seasonal_prices = kate_toms_get_seasonal_prices( $house->ID, $beginning_date, $ending_date, $all_period_keys );
					if ( ! empty( $seasonal_prices ) ) :
						?>
						<div class="wp-block-group house_meta seasp" style="border-top-color:var(--wp--preset--color--tertiary);border-top-width:1px;padding-top:var(--wp--preset--spacing--30);padding-right:var(--wp--preset--spacing--30);padding-bottom:var(--wp--preset--spacing--30);padding-left:var(--wp--preset--spacing--30)">
							<?php
							$all_prices_with_from = get_post_meta( $house->ID, 'all_prices_with_from', true );

							foreach ( $seasonal_prices as $period_label => $rates ) :
								// Skip unavailable periods (indicated by -2).
								if ( in_array( '-2', $rates, true ) ) {
									continue;
								}

								// Pluralize period names if multiple rates.
								$display_period = $period_label;
								if ( count( $rates ) > 1 ) {
									$display_period = $period_label . 's';
									$display_period = str_replace( 'ss', 's', $display_period );
								}

								// Check if any rates have "from" indicator (+).
								$has_from_indicator = false;
								foreach ( $rates as $rate ) {
									if ( strstr( $rate, '+' ) ) {
										$has_from_indicator = true;
										break;
									}
								}

								// Clean rates (strip +, *, spaces).
								$clean_rates = array_map(
									function ( $rate ) {
										return str_replace( array( '+', '*', ' ' ), '', $rate );
									},
									$rates
								);

								// Get minimum price.
								$min_price = kate_toms_convert_from_price( $clean_rates );

								// Determine if we show "from" or exact price.
								$show_from = ( $all_prices_with_from || count( $rates ) > 1 || $has_from_indicator );
								?>
								<p class="has-x-small-font-size" style="margin: 0;">
									<?php echo esc_html( ucfirst( $display_period ) ); ?>
									<?php echo $show_from ? ' from ' : ' - '; ?>
									&pound;<?php echo esc_html( $min_price ); ?>
								</p>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>
				</div>
			<?php endforeach; ?>
		</div>

		<!-- Skeleton Placeholders (shown during loading) -->
		<div class="house-seasonal-skeletons" data-wp-bind--hidden="!context.isLoading">
			<?php for ( $i = 0; $i < 4; $i++ ) : ?>
				<div class="house-card-skeleton">
					<div class="skeleton-image"></div>
					<div class="skeleton-title"></div>
					<div class="skeleton-description">
						<div class="skeleton-line"></div>
						<div class="skeleton-line skeleton-line--short"></div>
					</div>
					<div class="skeleton-footer">
						<div class="skeleton-line skeleton-line--tiny"></div>
						<div class="skeleton-line skeleton-line--tiny"></div>
					</div>
				</div>
			<?php endfor; ?>
		</div>

		<!-- Adverts Container (shown when loading is complete) -->
		<div class="house-seasonal-adverts" data-wp-bind--hidden="context.hasMore">
			<?php
			// Render initial adverts if there's only 1 page.
			if ( ! empty( $initial_adverts ) ) :
				foreach ( $initial_adverts as $advert ) :
					?>
					<!-- Advert Placeholder Card -->
					<div class="wp-block-group has-white-background-color has-background advert-placeholder" style="min-height:365px">
						<img src="<?php echo esc_url( $advert['image_url'] ); ?>"
							style="width: 100%; height: 368px; display: block; object-fit: cover;"
							alt="Advertisement">
					</div>
					<?php
				endforeach;
			endif;
			?>
		</div>

	</div>

	<!-- Scroll Sentinel (triggers loading when visible) -->
	<div
		class="house-seasonal-sentinel"
		data-wp-bind--hidden="!context.hasMore"
		data-wp-on-async-window--scroll="actions.checkScroll"
		data-wp-init="callbacks.init"
	></div>
</div>

<?php
wp_reset_postdata();
?>
