<?php
/**
 * Server-side rendering for the single-house-display block
 *
 * @param array $attributes Block attributes
 * @param string $content Block content
 * @param WP_Block $block Block instance
 * @return string Rendered block HTML
 */

// Debug: Always return something to test if render.php is being called
// return '<div class="wp-block-kate-toms-core-single-house-display"><p>DEBUG: Render function called</p></div>';

// Log to debug that render function is being called
error_log('Single House Display render.php called with attributes: ' . print_r($attributes, true));

// Early return if no house is selected
if (empty($attributes['selectedHouse'])) {
	return '<div class="wp-block-kate-toms-core-single-house-display"><p>' . __('No house selected.', 'kate-toms-core') . '</p></div>';
}

$house_id = absint($attributes['selectedHouse']);
$display_style = sanitize_text_field($attributes['displayStyle'] ?? 'coast');

error_log('Processing house ID: ' . $house_id . ' with style: ' . $display_style);

// Get the house post
$house_post = get_post($house_id);
if (!$house_post || $house_post->post_type !== 'houses') {
	error_log('House not found or wrong post type. Post type: ' . ($house_post ? $house_post->post_type : 'null'));
	return '<div class="wp-block-kate-toms-core-single-house-display"><p>' . __('House not found.', 'kate-toms-core') . '</p></div>';
}

// Check if it's a parent house (not a child page)
if ($house_post->post_parent != 0) {
	error_log('House is not a parent house. Parent ID: ' . $house_post->post_parent);
	return '<div class="wp-block-kate-toms-core-single-house-display"><p>' . __('Please select a parent house.', 'kate-toms-core') . '</p></div>';
}

error_log('House validation passed. Title: ' . $house_post->post_title);

// Get house meta data
$brief_description = get_post_meta($house_id, 'brief_description', true);
$sleeps_min = get_post_meta($house_id, 'sleeps_min', true);
$sleeps_max = get_post_meta($house_id, 'sleeps_max', true);
$location_text = get_post_meta($house_id, 'location_text', true);

error_log('Meta data - Brief: ' . $brief_description . ', Sleeps: ' . $sleeps_min . '-' . $sleeps_max . ', Location: ' . $location_text);

// Get featured image
$featured_image = get_the_post_thumbnail($house_id, 'full');
$house_url = get_permalink($house_id);

error_log('Featured image length: ' . strlen($featured_image) . ', URL: ' . $house_url);

// Set background color based on display style
$background_colors = [
	'coast' => 'coloreight',
	'cotswolds' => 'colorfive',
	'country' => 'titlecolorthree',
	'town' => 'coloreight',
];

$background_color = $background_colors[$display_style] ?? 'coloreight';

// Build the HTML using the pattern structure
$html = '<div class="wp-block-kate-toms-core-single-house-display" style="border: 5px solid red; padding: 20px; background: yellow; min-height: 400px;">';
$html .= '<div class="wp-block-group has-white-background-color has-background" style="min-height:365px">';

// Featured image with link
if ($featured_image) {
	$html .= '<a href="' . esc_url($house_url) . '">' . $featured_image . '</a>';
}

// Title with link and background color
$html .= '<div class="wp-block-post-title has-text-align-center">';
$html .= '<h2 class="wp-block-post-title__link has-' . esc_attr($background_color) . '-background-color has-white-color has-background has-link-color" style="padding-top:var(--wp--preset--spacing--40);padding-bottom:var(--wp--preset--spacing--40);font-style:normal;font-weight:600;font-size:var(--wp--preset--font-size--small)">';
$html .= '<a href="' . esc_url($house_url) . '" style="color:var(--wp--preset--color--white)">' . esc_html($house_post->post_title) . '</a>';
$html .= '</h2>';
$html .= '</div>';

// Description section
$html .= '<div class="wp-block-group" style="padding-top:var(--wp--preset--spacing--30);padding-right:var(--wp--preset--spacing--30);padding-bottom:var(--wp--preset--spacing--30);padding-left:var(--wp--preset--spacing--30)">';
$html .= '<p class="has-x-small-font-size">' . esc_html($brief_description) . '</p>';
$html .= '</div>';

// Footer section with sleeps and location
$html .= '<div class="wp-block-group" style="border-top-color:var(--wp--preset--color--tertiary);border-top-width:1px;padding-top:var(--wp--preset--spacing--30);padding-right:var(--wp--preset--spacing--30);padding-bottom:var(--wp--preset--spacing--30);padding-left:var(--wp--preset--spacing--30)">';
$html .= '<div class="wp-block-group" style="display:flex;flex-wrap:nowrap;justify-content:space-between">';

// Sleeps section
$html .= '<div class="wp-block-group" style="display:flex;flex-wrap:nowrap;gap:0.2em">';
$html .= '<p class="has-x-small-font-size">Sleeps </p>';
$html .= '<p class="has-x-small-font-size">' . esc_html($sleeps_min) . '</p>';
$html .= '<p class="has-x-small-font-size"> to </p>';
$html .= '<p class="has-x-small-font-size">' . esc_html($sleeps_max) . '</p>';
$html .= '</div>';

// Location section
$html .= '<div class="wp-block-group" style="display:flex;flex-wrap:nowrap;justify-content:right">';
$html .= '<p class="has-text-align-right has-x-small-font-size">' . esc_html($location_text) . '</p>';
$html .= '</div>';

$html .= '</div>'; // Close flex container
$html .= '</div>'; // Close footer group
$html .= '</div>'; // Close main group
$html .= '</div>'; // Close wrapper

return $html;