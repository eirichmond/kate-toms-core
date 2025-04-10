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
$args = array(
	'post_type'      => 'houses',
	'posts_per_page' => 12,
	'post_status'    => 'publish',
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


// Prepare context data.
$context = wp_json_encode(
	array(
		'blockId'         => $attributes['blockId'],
		'defaultLocation' => $attributes['defaultLocation'] ?? '',
	)
);

?>

<div
	<?php echo wp_kses_post( get_block_wrapper_attributes() ); ?>
	data-wp-interactive="kate-toms-house-filter"
	data-wp-context="<?php echo esc_attr( $context ); ?>"
	data-block-id="<?php echo esc_attr( $attributes['blockId'] ); ?>"
>
	<div class="houses-grid">
		<?php
		if ( $query->have_posts() ) {
			while ( $query->have_posts() ) {
				$query->the_post();
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
</div>
