<?php
/**
 * House Load Search Block Template.
 *
 * @package Kate_Toms_Core
 *
 * @param array    $attributes Block attributes.
 * @param string   $content    Block default content.
 * @param WP_Block $block      Block instance.
 */

$posts_per_page    = $attributes['postsPerPage'] ?? 20;
$location_term_ids = $attributes['locationTermIds'] ?? array();
$feature_term_ids  = $attributes['featureTermIds'] ?? array();
$size_term_ids     = $attributes['sizeTermIds'] ?? array();
$type_term_ids     = $attributes['typeTermIds'] ?? array();
$occasion_term_ids = $attributes['occasionTermIds'] ?? array();

// Map location term IDs to title background colors.
$location_color_map = array(
	604 => 'colorfive',       // Cotswolds.
	810 => 'coloreight',      // Coast.
	790 => 'titlecolorthree', // Country.
	603 => 'coloreight',      // Town.
);

// Map location term IDs to advert location keys.
$location_key_map = array(
	604 => 'cotswolds',
	810 => 'coast',
	790 => 'country',
	603 => 'town',
);

// Determine title color and location key from first selected location, or use defaults.
$first_location_id = ! empty( $location_term_ids ) ? $location_term_ids[0] : 0;
$title_bg_color    = isset( $location_color_map[ $first_location_id ] ) ? $location_color_map[ $first_location_id ] : 'colorfive';
$location_key      = isset( $location_key_map[ $first_location_id ] ) ? $location_key_map[ $first_location_id ] : 'cotswolds';

// Build the query arguments.
$query_args = array(
	'post_type'      => 'houses',
	'post_status'    => 'publish',
	'posts_per_page' => $posts_per_page,
	'paged'          => 1,
	'orderby'        => 'meta_value_num',
	'order'          => 'DESC',
	'meta_key'       => 'sleeps_max',
	'meta_query'     => array(
		array(
			'key'     => 'sleeps_max',
			'compare' => 'EXISTS',
			'type'    => 'NUMERIC',
		),
	),
);

// Build taxonomy query for location and/or features.
$tax_query = array();

if ( ! empty( $location_term_ids ) ) {
	$tax_query[] = array(
		'taxonomy' => 'location',
		'field'    => 'term_id',
		'terms'    => $location_term_ids,
		'operator' => 'IN',
	);
}

if ( ! empty( $feature_term_ids ) ) {
	$tax_query[] = array(
		'taxonomy' => 'feature',
		'field'    => 'term_id',
		'terms'    => $feature_term_ids,
		'operator' => 'IN',
	);
}

if ( ! empty( $size_term_ids ) ) {
	$tax_query[] = array(
		'taxonomy' => 'size',
		'field'    => 'term_id',
		'terms'    => $size_term_ids,
		'operator' => 'IN',
	);
}

if ( ! empty( $type_term_ids ) ) {
	$tax_query[] = array(
		'taxonomy' => 'type',
		'field'    => 'term_id',
		'terms'    => $type_term_ids,
		'operator' => 'IN',
	);
}

if ( ! empty( $occasion_term_ids ) ) {
	$tax_query[] = array(
		'taxonomy' => 'occasion',
		'field'    => 'term_id',
		'terms'    => $occasion_term_ids,
		'operator' => 'IN',
	);
}

if ( ! empty( $tax_query ) ) {
	if ( count( $tax_query ) > 1 ) {
		$tax_query['relation'] = 'AND';
	}
	$query_args['tax_query'] = $tax_query;
}

// Execute the query.
$houses_query = new WP_Query( $query_args );
$total_houses = $houses_query->found_posts;
$total_pages  = $houses_query->max_num_pages;

if ( ! $houses_query->have_posts() ) {
	echo '<p>' . esc_html__( 'No houses found.', 'kate-toms-core' ) . '</p>';
	return;
}

// Calculate adverts needed for initial render (when only 1 page or to fill the grid).
$initial_adverts = array();
if ( $total_pages <= 1 ) {
	$remainder = $total_houses % 4;
	if ( $remainder > 0 ) {
		$adverts_needed = 4 - $remainder;
		if ( class_exists( 'Kate_Toms_Core_Admin' ) ) {
			$admin           = new Kate_Toms_Core_Admin( 'kate-toms-core', '1.0.0' );
			$initial_adverts = $admin->get_adverts_for_location( $location_key, $adverts_needed );
		}
	}
}

// Set up interactivity API context.
$context = array(
	'postsPerPage'   => $posts_per_page,
	'currentPage'    => 1,
	'totalPages'     => $total_pages,
	'totalHouses'    => $total_houses,
	'isLoading'      => false,
	'hasMore'        => $total_pages > 1,
	'titleBgColor'    => $title_bg_color,
	'locationTermIds'  => $location_term_ids,
	'locationKey'      => $location_key,
	'featureTermIds'   => $feature_term_ids,
	'sizeTermIds'      => $size_term_ids,
	'typeTermIds'      => $type_term_ids,
	'occasionTermIds'  => $occasion_term_ids,
);

?>

<div
	<?php echo wp_kses_post( get_block_wrapper_attributes( array( 'class' => 'house-load-search' ) ) ); ?>
	data-wp-interactive="kate-toms-house-load-search"
	data-wp-context='<?php echo wp_json_encode( $context ); ?>'
>
	<div class="house-load-search-grid" data-wp-class--is-loading="context.isLoading">
		<style>
		.house-load-search-grid {
			display: grid;
			grid-template-columns: repeat(4, 1fr);
			gap: 20px;
		}
		.house-load-search-results,
		.house-load-search-adverts,
		.house-load-search-skeletons {
			display: contents;
		}
		@media (max-width: 1200px) {
			.house-load-search-grid { grid-template-columns: repeat(3, 1fr); }
		}
		@media (max-width: 900px) {
			.house-load-search-grid { grid-template-columns: repeat(2, 1fr); }
		}
		@media (max-width: 600px) {
			.house-load-search-grid { grid-template-columns: 1fr; }
		}
		</style>

		<div class="house-load-search-results" data-wp-on--append-houses="actions.appendHouses">
			<?php
			while ( $houses_query->have_posts() ) :
				$houses_query->the_post();
				?>
				<!-- House Card using theme pattern structure -->
				<div class="wp-block-group has-white-background-color has-background house-card" style="min-height:365px">

					<a href="<?php the_permalink(); ?>">
						<!-- Featured Image -->
						<?php if ( has_post_thumbnail() ) : ?>
							<?php the_post_thumbnail( 'house_search', array( 'style' => 'width: 100%; height: auto; display: block;' ) ); ?>
						<?php endif; ?>

						<!-- Post Title with styling from pattern -->
						<h3 class="wp-block-heading has-text-align-center has-small-font-size has-white-color has-<?php echo esc_attr( $title_bg_color ); ?>-background-color"
						style="margin-top:0;margin-bottom:0;padding-top:var(--wp--preset--spacing--40);padding-right:var(--wp--preset--spacing--40);padding-bottom:var(--wp--preset--spacing--40);padding-left:var(--wp--preset--spacing--40);font-style:normal;font-weight:600;font-size:var(--wp--preset--font-size--small)">
							<?php the_title(); ?>
						</h3>

						<!-- Brief Description -->
						<div class="wp-block-group" style="padding-top:var(--wp--preset--spacing--30);padding-right:var(--wp--preset--spacing--30);padding-bottom:var(--wp--preset--spacing--30);padding-left:var(--wp--preset--spacing--30)">
							<?php
							$brief_description = get_post_meta( get_the_ID(), 'brief_description', true );
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
									$sleeps_min = get_post_meta( get_the_ID(), 'sleeps_min', true );
									$sleeps_max = get_post_meta( get_the_ID(), 'sleeps_max', true );
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
									$location_text = get_post_meta( get_the_ID(), 'location_text', true );
									if ( $location_text ) :
										?>
										<p class="has-text-align-right has-x-small-font-size" style="margin: 0;"><?php echo esc_html( $location_text ); ?></p>
									<?php endif; ?>
								</div>
							</div>
						</div>

					</a>
				</div>
			<?php endwhile; ?>
		</div>



		<!-- Skeleton Placeholders (shown during loading) -->
		<!-- TODO: Add back data-wp-bind--hidden="!state.isLoading" when done styling -->
		<!-- TODO: Add back data-wp-bind--hidden="!context.isLoading" when done styling -->
		<div class="house-load-search-skeletons" data-wp-bind--hidden="!context.isLoading">
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
		<div class="house-load-search-adverts" data-wp-bind--hidden="context.hasMore">
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
		class="house-load-search-sentinel"
		data-wp-bind--hidden="!context.hasMore"
		data-wp-on-async-window--scroll="actions.checkScroll"
		data-wp-init="callbacks.init"
	></div>
</div>

<?php
wp_reset_postdata();
?>
