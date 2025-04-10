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
				echo do_blocks( '<!-- wp:pattern {"slug":"katomswold/house-card-search-cotswolds"} /-->' );
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
	
	<div 
		class="houses-loading-overlay" 
		data-wp-bind--hidden="!state.isLoading"
	>
		<div class="houses-loading-spinner">
			<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24">
				<path fill="currentColor" d="M12,1A11,11,0,1,0,23,12,11,11,0,0,0,12,1Zm0,19a8,8,0,1,1,8-8A8,8,0,0,1,12,20Z" opacity=".25"/>
				<path fill="currentColor" d="M12,4a8,8,0,0,1,7.89,6.7A1.53,1.53,0,0,0,21.38,12h0a1.5,1.5,0,0,0,1.48-1.75,11,11,0,0,0-21.72,0A1.5,1.5,0,0,0,2.62,12h0a1.53,1.53,0,0,0,1.49-1.3A8,8,0,0,1,12,4Z">
				<animateTransform attributeName="transform" type="rotate" dur="0.75s" values="0 12 12;360 12 12" repeatCount="indefinite"/>
				</path>
			</svg>

		</div>
	</div>
</div>
