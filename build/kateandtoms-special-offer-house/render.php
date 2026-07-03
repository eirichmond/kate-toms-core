<?php
/**
 * Kate & Tom's Single House Block Template.
 *
 * @package kate-toms-core
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Block default content.
 * @var WP_Block $block      Block instance.
 */

// Random Placeholder mode: override the house entirely and render a single
// advert card (plain image, no link) filling the same slot. Adverts come from
// the same location-based system used by houses-filtered-results.
if ( ! empty( $attributes['isPlaceholder'] ) ) {
	$placeholder_location = ! empty( $attributes['placeholderLocation'] )
		? sanitize_key( $attributes['placeholderLocation'] )
		: '';

	$advert = null;
	if ( '' !== $placeholder_location && class_exists( 'Kate_Toms_Core_Admin' ) ) {
		$admin   = new Kate_Toms_Core_Admin( 'kate-toms-core', '1.0.0' );
		$adverts = $admin->get_adverts_for_location( $placeholder_location, 100 );
		if ( ! empty( $adverts ) ) {
			// New random advert each render, hence "Random Placeholder".
			$advert = $adverts[ array_rand( $adverts ) ];
		}
	}
	?>
	<div <?php echo wp_kses_post( get_block_wrapper_attributes( array( 'class' => 'wp-block-kate-toms-core-kateandtoms-single-house' ) ) ); ?>>
		<div class="wp-block-group has-white-background-color has-background special-offer-advert-placeholder" style="min-height:365px;display:flex;overflow:hidden">
			<?php if ( $advert && ! empty( $advert['image_url'] ) ) : ?>
				<img src="<?php echo esc_url( $advert['image_url'] ); ?>" alt="Advertisement" style="width:100%;height:100%;min-height:365px;object-fit:cover;display:block;" />
			<?php elseif ( is_user_logged_in() ) : ?>
				<p style="margin:auto;padding:20px;text-align:center;color:#721c24;">
					<?php
					echo esc_html(
						'' === $placeholder_location
							? __( 'Random Placeholder is on — choose a location in the block settings.', 'kate-toms-core' )
							: __( 'No adverts found for the selected location.', 'kate-toms-core' )
					);
					?>
				</p>
			<?php endif; ?>
		</div>
	</div>
	<?php
	return;
}

// Fallback house ID (Bixley Manor)
$fallback_house_id = 104964;

// Get house ID from attributes or use fallback
$house_id = !empty($attributes['selectedPostId']) ? absint($attributes['selectedPostId']) : $fallback_house_id;

// Get offer details from attributes
$offer = !empty($attributes['offer']) ? sanitize_text_field($attributes['offer']) : '';

$offer_date = !empty($attributes['offerDate']) ? sanitize_text_field($attributes['offerDate']) : '';

// Get the house post
$house_post = get_post($house_id);

// If selected post doesn't exist or isn't a house, use fallback
if (!$house_post || $house_post->post_type !== 'houses' || $house_post->post_status !== 'publish') {
	$house_id = $fallback_house_id;
	$house_post = get_post($house_id);
	
	// If user is logged in (editor context), show an error message
	if (is_user_logged_in() && !empty($attributes['selectedPostId']) && $attributes['selectedPostId'] !== $fallback_house_id) {
		$error_message = '<div class="wp-block-kate-toms-core-kateandtoms-single-house-error" style="padding: 20px; background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; border-radius: 4px; margin-bottom: 20px;">';
		$error_message .= '<strong>' . __('Notice:', 'kate-toms-core') . '</strong> ';
		$error_message .= __('The selected house is no longer available. Showing fallback house instead.', 'kate-toms-core');
		$error_message .= '</div>';
	}
}

// Final check - if fallback house doesn't exist, show error
if (!$house_post || $house_post->post_type !== 'houses' || $house_post->post_status !== 'publish') {
	echo '<div class="wp-block-kate-toms-core-kateandtoms-single-house"><p>' . __('House not found.', 'kate-toms-core') . '</p></div>';
	return;
}

// Check if it's a parent house (not a child page)
if ($house_post->post_parent != 0) {
	echo '<div class="wp-block-kate-toms-core-kateandtoms-single-house"><p>' . __('Please select a parent house.', 'kate-toms-core') . '</p></div>';
	return;
}

// Always use special offer pattern
$pattern_slug = 'katomswold/house-card-search-special-offer';

// Store offer attributes in a global variable for the pattern to access
global $special_offer_attributes;
$special_offer_attributes = [
	'offer' => $offer,
	'offerDate' => $offer_date,
];

// Set up global post context (same as houses-filtered-results)
global $post;
$original_post = $post;
$post = $house_post;

setup_postdata($post);

?>

<div <?php echo wp_kses_post(get_block_wrapper_attributes(['class' => 'wp-block-kate-toms-core-kateandtoms-single-house'])); ?>>
	<?php
	// Show error message if applicable
	if (isset($error_message)) {
		echo $error_message;
	}

	// NOTE: We deliberately do NOT render this via
	// do_blocks('<!-- wp:pattern {"slug":...} /-->'). Core's
	// WP_Block_Patterns_Registry includes a file-based pattern only once,
	// caches the resulting content string and unsets its filePath — so the
	// per-instance offer that this pattern bakes in via the
	// $special_offer_attributes global would be captured from the FIRST
	// block on the page and reused for every subsequent one. Instead we
	// include the pattern file directly so its PHP re-runs for each block
	// instance with this instance's global set, producing unique output.
	$pattern_file = get_theme_file_path('patterns/house-card-search-special-offer.php');
	if (file_exists($pattern_file)) {
		ob_start();
		include $pattern_file;
		echo do_blocks(ob_get_clean());
	} else {
		// Fallback to the registered pattern if the file can't be located.
		echo do_blocks('<!-- wp:pattern {"slug":"' . $pattern_slug . '"} /-->');
	}
	?>
</div>

<?php
// Restore original post context
$post = $original_post;
if ($post) {
	setup_postdata($post);
} else {
	wp_reset_postdata();
}
?> 
