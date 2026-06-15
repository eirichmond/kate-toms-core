<?php
/**
 * Houses Filtered Results Block Template.
 *
 * @package kate-toms-core
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Block default content.
 * @var WP_Block $block      Block instance.
 */

// Generate a unique block ID if not set.
if ( empty( $attributes['blockId'] ) ) {
	$attributes['blockId'] = wp_unique_id( 'houses-results-' );
}

// Get initial houses query.
// Order by sleeps_max (largest first), excluding houses without it — mirrors
// the kate-toms-core/house-load-search block so both lists rank identically.
$args = array(
	'post_type'      => 'houses',
	'posts_per_page' => 20,
	'post_status'    => 'publish',
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


// Add default location to query if set.
if ( ! empty( $attributes['defaultLocation'] ) ) {
	$args['tax_query'] = array(
		array(
			'taxonomy' => 'location',
			'field'    => 'term_id',
			'terms'    => $attributes['defaultLocation'],
		),
	);
}

$query = new WP_Query( $args );

// Pagination state for the infinite-scroll sentinel. Mirrors the
// kate-toms-core/house-load-search block: page 1 is server-rendered, further
// pages are appended client-side from the /houses endpoint.
$per_page  = (int) $args['posts_per_page'];
$has_more  = $query->max_num_pages > 1;


// Prepare context data.
$context = wp_json_encode(
	array(
		'blockId'         => $attributes['blockId'],
		'defaultLocation' => $attributes['defaultLocation'] ?? '',
		'isLoadingMore'   => false,
	)
);


?>

<div
	<?php echo wp_kses_post( get_block_wrapper_attributes() ); ?>
	data-wp-interactive="kate-toms-house-filter"
	data-wp-context="<?php echo esc_attr( $context ); ?>"
	data-block-id="<?php echo esc_attr( $attributes['blockId'] ); ?>"
	data-current-page="1"
	data-per-page="<?php echo esc_attr( $per_page ); ?>"
	data-has-more="<?php echo $has_more ? 'true' : 'false'; ?>"
>
	<div class="houses-grid">
		<?php
		if ( $query->have_posts() ) {
			$house_count = 0;
			while ( $query->have_posts() ) {
				$query->the_post();
				$house_count++;
				?>

				<?php
				// Use your custom pattern.
					if( 604 == $attributes['defaultLocation'] ) {
						echo do_blocks( '<!-- wp:pattern {"slug":"katomswold/house-card-search-cotswolds"} /-->' );
					} elseif ( 810 == $attributes['defaultLocation'] ) {
						echo do_blocks( '<!-- wp:pattern {"slug":"katomswold/house-card-search-coast"} /-->' );
					} elseif ( 790 == $attributes['defaultLocation'] ) {
						echo do_blocks( '<!-- wp:pattern {"slug":"katomswold/house-card-search-country"} /-->' );
					} elseif ( 603 == $attributes['defaultLocation'] ) {
						echo do_blocks( '<!-- wp:pattern {"slug":"katomswold/house-card-search-town"} /-->' );
					}
				?>

				<?php
			}

			// Check if we need adverts to fill the row. Only when this is the
			// only page — otherwise adverts are appended client-side once the
			// final page loads (see view.js).
			$remainder = $house_count % 4;
			if ( ! $has_more && $remainder > 0 ) {
				$adverts_needed = 4 - $remainder;

				// Determine location key for adverts
				$location_key = '';
				if ( 604 == $attributes['defaultLocation'] ) {
					$location_key = 'cotswolds';
				} elseif ( 810 == $attributes['defaultLocation'] ) {
					$location_key = 'coast';
				} elseif ( 790 == $attributes['defaultLocation'] ) {
					$location_key = 'country';
				} elseif ( 603 == $attributes['defaultLocation'] ) {
					$location_key = 'town';
				}

				// Get adverts if we have a location key
				if ( ! empty( $location_key ) && class_exists( 'Kate_Toms_Core_Admin' ) ) {
					$admin = new Kate_Toms_Core_Admin( 'kate-toms-core', '1.0.0' );
					$adverts = $admin->get_adverts_for_location( $location_key, $adverts_needed );

					// Output advert placeholders
					foreach ( $adverts as $advert ) {
						?>
						<!-- Advert Placeholder Card -->
						<div class="house-card advert-placeholder">
							<div class="house-card__image">
								<img src="<?php echo esc_url( $advert['image_url'] ); ?>"
									alt="Advertisement"
									style="width: 100%; height: 100%; object-fit: cover;">
							</div>
						</div>
						<?php
					}
				}
			}
		} else {
			?>
			<div class="houses-filter__no-results">
				<p>No houses found.</p>
			</div>
			<?php
		}
		wp_reset_postdata();
		?>
	</div>

	<!-- Scroll sentinel: triggers loading the next page when scrolled into view. -->
	<div
		class="houses-filtered-results-sentinel"
		data-wp-on-async-window--scroll="actions.checkScroll"
		data-wp-init="callbacks.init"
	></div>
</div>
