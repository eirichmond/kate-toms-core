<?php
/**
 * House Landing Pages Block Template.
 *
 * @param array    $attributes Block attributes.
 * @param string   $content    Block default content.
 * @param WP_Block $block      Block instance.
 */

$posts_per_page = $attributes['postsPerPage'] ?? -1;
$order_by = $attributes['orderBy'] ?? 'meta_value_num';
$order = $attributes['order'] ?? 'desc';
$meta_key = $attributes['metaKey'] ?? 'sleeps_max';
$pattern_style = $attributes['patternStyle'] ?? 'coast';
$taxonomy_filters = $attributes['taxonomyFilters'] ?? [];

// Map pattern styles to background colors and location taxonomy slugs
$pattern_config = [
	'coast' => [
		'color' => 'coloreight',
		'taxonomy_slug' => 'sea',
		'name' => 'By the Coast'
	],
	'cotswolds' => [
		'color' => 'colorfive',
		'taxonomy_slug' => 'cotswolds', 
		'name' => 'In the Cotswolds'
	],
	'country' => [
		'color' => 'titlecolorthree',
		'taxonomy_slug' => 'country',
		'name' => 'In the Country'
	],
	'town' => [
		'color' => 'coloreight',
		'taxonomy_slug' => 'town',
		'name' => 'In town'
	]
];

$pattern_data = $pattern_config[$pattern_style] ?? $pattern_config['coast'];
$title_bg_color = $pattern_data['color'];
$location_slug = $pattern_data['taxonomy_slug'];
$location_name = $pattern_data['name'];

// Build the tax_query array
$tax_query = array(
	'relation' => 'AND', // All taxonomy filters must match
	array(
		'taxonomy' => 'location',
		'field'    => 'slug',
		'terms'    => $location_slug,
	),
);

// Add additional taxonomy filters if they exist
foreach (array ( 'size', 'activity', 'type', 'occasion', 'feature', 'location' ) as $taxonomy) {
	if (!empty($taxonomy_filters[$taxonomy]) && is_array($taxonomy_filters[$taxonomy])) {
		$tax_query[] = array(
			'taxonomy' => $taxonomy,
			'field'    => 'slug',
			'terms'    => $taxonomy_filters[$taxonomy],
			'operator' => 'IN', // Match any of the selected terms
		);
	}
}

// Build the query arguments
$query_args = array(
	'post_type' => 'houses',
	'post_status' => 'publish',
	'posts_per_page' => $posts_per_page,
	'orderby' => $order_by,
	'order' => $order,
	'tax_query' => $tax_query,
);

// Add meta query and meta_key for meta_value_num ordering
if ($order_by === 'meta_value_num') {
	$query_args['meta_key'] = $meta_key;
	$query_args['meta_query'] = array(
		array(
			'key' => $meta_key,
			'compare' => 'EXISTS',
			'type' => 'NUMERIC'
		)
	);
}

// Execute the query
$houses_query = new WP_Query($query_args);

if (!$houses_query->have_posts()) {
	echo '<p>' . __('No houses found.', 'kate-toms-core') . '</p>';
	return;
}

// Get houses and check if we need adverts to fill rows
$houses = $houses_query->posts;
$house_count = count($houses);
$remainder = $house_count % 4;
$adverts = array();

if ($remainder > 0) {
	// We need adverts to fill the row
	$adverts_needed = 4 - $remainder;
	
	// Get admin class to access advert methods
	if (class_exists('Kate_Toms_Core_Admin')) {
		$admin = new Kate_Toms_Core_Admin('kate-toms-core', '1.0.0');
		$location_key = $pattern_style; // Use pattern style as location key
		$adverts = $admin->get_adverts_for_location($location_key, $adverts_needed);
	}
}

?>

<div <?php echo get_block_wrapper_attributes(['class' => 'house-landing-pages']); ?>>

	<div class="house-landing-grid" style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px;">
		<style>
		@media (max-width: 1200px) {
			.house-landing-grid { grid-template-columns: repeat(3, 1fr) !important; }
		}
		@media (max-width: 900px) {
			.house-landing-grid { grid-template-columns: repeat(2, 1fr) !important; }
		}
		@media (max-width: 600px) {
			.house-landing-grid { grid-template-columns: 1fr !important; }
		}
		</style>
		<?php while ($houses_query->have_posts()) : $houses_query->the_post(); ?>
			<!-- House Card using theme pattern structure -->
			<div class="wp-block-group has-white-background-color has-background" style="min-height:365px">

				<a href="<?php the_permalink(); ?>">
					<!-- Featured Image -->
					<?php if ( has_post_thumbnail() ): ?>
						<?php the_post_thumbnail( 'house_search', array( 'style' => 'width: 100%; height: auto; display: block;' )) ?>
					<?php endif; ?>

					<!-- Post Title with styling from pattern -->
					<h3 class="wp-block-heading has-text-align-center has-small-font-size has-white-color has-<?php echo esc_attr($title_bg_color); ?>-background-color" 
					style="margin-top:0;margin-bottom:0;padding-top:var(--wp--preset--spacing--40);padding-right:var(--wp--preset--spacing--40);padding-bottom:var(--wp--preset--spacing--40);padding-left:var(--wp--preset--spacing--40);font-style:normal;font-weight:600;font-size:var(--wp--preset--font-size--small)">
						<?php the_title(); ?>
					</h3>

					<!-- Brief Description -->
					<div class="wp-block-group" style="padding-top:var(--wp--preset--spacing--30);padding-right:var(--wp--preset--spacing--30);padding-bottom:var(--wp--preset--spacing--30);padding-left:var(--wp--preset--spacing--30)">
						<?php 
						$brief_description = get_post_meta(get_the_ID(), 'brief_description', true);
						if ($brief_description): 
						?>
							<p class="has-x-small-font-size"><?php echo esc_html($brief_description); ?></p>
						<?php endif; ?>
					</div>

					<!-- Sleeps and Location Info -->
					<div class="wp-block-group" style="border-top-color:var(--wp--preset--color--tertiary);border-top-width:1px;padding-top:var(--wp--preset--spacing--30);padding-right:var(--wp--preset--spacing--30);padding-bottom:var(--wp--preset--spacing--30);padding-left:var(--wp--preset--spacing--30)">
						<div class="wp-block-group" style="display: flex; justify-content: space-between; align-items: center;">
							<!-- Sleeps Info -->
							<div class="wp-block-group" style="display: flex; align-items: center; gap: 0.2em;">
								<?php 
								$sleeps_min = get_post_meta(get_the_ID(), 'sleeps_min', true);
								$sleeps_max = get_post_meta(get_the_ID(), 'sleeps_max', true);
								if ($sleeps_max): 
								?>
									<p class="has-x-small-font-size" style="margin: 0;">Sleeps </p>
									<?php if ($sleeps_min): ?>
										<p class="has-x-small-font-size" style="margin: 0;"><?php echo esc_html($sleeps_min); ?></p>
										<p class="has-x-small-font-size" style="margin: 0;"> to </p>
									<?php endif; ?>
									<p class="has-x-small-font-size" style="margin: 0;"><?php echo esc_html($sleeps_max); ?></p>
								<?php endif; ?>
							</div>

							<!-- Location Info -->
							<div class="wp-block-group">
								<?php 
								$location_text = get_post_meta(get_the_ID(), 'location_text', true);
								if ($location_text): 
								?>
									<p class="has-text-align-right has-x-small-font-size" style="margin: 0;"><?php echo esc_html($location_text); ?></p>
								<?php endif; ?>
							</div>
						</div>
					</div>

				</a>
			</div>
		<?php endwhile; ?>

		<?php 
		// Add advert placeholders if needed
		if (!empty($adverts)) :
			foreach ($adverts as $advert) :
		?>
			<!-- Advert Placeholder Card -->
			<div class="wp-block-group has-white-background-color has-background advert-placeholder" style="min-height:365px">
				<!-- Advert Image -->
				<img src="<?php echo esc_url($advert['image_url']); ?>" 
				     style="width: 100%; height: 368px; display: block; object-fit: cover;" 
				     alt="Advertisement">
			</div>
		<?php 
			endforeach;
		endif; 
		?>
	</div>
</div>

<?php
wp_reset_postdata();
?>
