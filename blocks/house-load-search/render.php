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

$posts_per_page = $attributes['postsPerPage'] ?? 20;

// Cotswolds style configuration.
$title_bg_color = 'colorfive';

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

// Execute the query.
$houses_query = new WP_Query( $query_args );
$total_houses = $houses_query->found_posts;
$total_pages  = $houses_query->max_num_pages;

if ( ! $houses_query->have_posts() ) {
	echo '<p>' . esc_html__( 'No houses found.', 'kate-toms-core' ) . '</p>';
	return;
}

// Set up interactivity API context.
$context = array(
	'postsPerPage' => $posts_per_page,
	'currentPage'  => 1,
	'totalPages'   => $total_pages,
	'totalHouses'  => $total_houses,
	'isLoading'    => false,
	'hasMore'      => $total_pages > 1,
	'titleBgColor' => $title_bg_color,
);

wp_interactivity_state(
	'kate-toms-house-load-search',
	array(
		'isLoading' => false,
		'hasMore'   => $total_pages > 1,
	)
);

?>

<div
	<?php echo wp_kses_post( get_block_wrapper_attributes( array( 'class' => 'house-load-search' ) ) ); ?>
	data-wp-interactive="kate-toms-house-load-search"
	data-wp-context='<?php echo wp_json_encode( $context ); ?>'
>
	<div class="house-load-search-grid" data-wp-class--is-loading="state.isLoading">
		<style>
		.house-load-search-grid {
			display: grid;
			grid-template-columns: repeat(4, 1fr);
			gap: 20px;
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
		<div class="house-load-search-skeletons" data-wp-bind--hidden="!state.isLoading">
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
		<div class="house-load-search-adverts" data-wp-bind--hidden="state.hasMore"></div>

	</div>

	<!-- Scroll Sentinel (triggers loading when visible) -->
	<div
		class="house-load-search-sentinel"
		data-wp-bind--hidden="!state.hasMore"
		data-wp-on-async-window--scroll="actions.checkScroll"
		data-wp-init="callbacks.init"
	></div>
</div>

<?php
wp_reset_postdata();
?>
