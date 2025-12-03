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

	// Use pattern slug approach like houses-filtered-results does
	echo do_blocks('<!-- wp:pattern {"slug":"' . $pattern_slug . '"} /-->');
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
