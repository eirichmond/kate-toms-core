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

// Get initial houses query.
$args = array(
	'post_type'      => 'houses',
	'posts_per_page' => 12,
    'post_status'    => 'publish'
);

$query = new WP_Query( $args );
?>

<div 
	<?php echo wp_kses_post( get_block_wrapper_attributes() ); ?>
	data-wp-interactive="kate-toms-house-filter"
>

    # I want to add a title attribute here, I also want to add setting that appends an aditional term attribute from the Locations taxonomy for this block only.

	<div class="houses-grid">


		<?php
		if ( $query->have_posts() ) {
			while ( $query->have_posts() ) {
				$query->the_post();
				?>
				<article class="house-card">
					<?php
					if ( has_post_thumbnail() ) {
						?>
						<div class="house-card__image">
							<?php the_post_thumbnail( 'medium' ); ?>
						</div>
						<?php
					}
					?>
					<div class="house-card__content">
						<h3 class="house-card__title">
							<a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
						</h3>
						<?php
						// Add house details.
						$location = get_the_terms( get_the_ID(), 'location' );
						if ( $location && ! is_wp_error( $location ) ) {
							echo '<div class="house-card__location">' . esc_html( $location[0]->name ) . '</div>';
						}

						$size = get_post_meta( get_the_ID(), 'house_size', true );
						if ( $size ) {
							echo '<div class="house-card__size">Sleeps ' . esc_html( $size ) . '</div>';
						}
						?>
					</div>
				</article>
				<?php
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
	
	<div 
		class="houses-loading-overlay" 
		data-wp-bind--hidden="!state.isLoading"
	>
		<div class="houses-loading-spinner"></div>
	</div>
</div>
