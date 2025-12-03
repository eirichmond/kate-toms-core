<?php
/**
 * House Booking Flow Block Template
 *
 * @package Kate_Toms_Core
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Get current post and check if we're on a booking page
global $post;

// Only render booking flow on booking pages (pages with 'book' in the name and a parent)
$is_booking_page = $post && $post->post_name === 'book' && $post->post_parent;


if ( ! $is_booking_page ) {
	// If not on a booking page, show a message for editors
	if ( current_user_can( 'edit_posts' ) ) {
		echo '<div class="booking-flow-notice" style="background: #fff3cd; border: 1px solid #ffeaa7; color: #856404; padding: 15px; border-radius: 4px; margin: 15px 0;">';
		echo '<p><strong>House Booking Flow Block:</strong> This block only displays content on house booking pages (pages named "book" with a parent house).</p>';
		echo '</div>';
	}
	return;
}

// Get URL parameters
$date_param = sanitize_text_field( wp_unslash( $_GET['d'] ?? '' ) );
$week_param = sanitize_text_field( wp_unslash( $_GET['week'] ?? '' ) );

// Generate unique ID for this block instance
$block_id = 'booking-flow-' . wp_generate_uuid4();

$wrapper_attributes = get_block_wrapper_attributes([
	'class' => 'house-booking-flow',
	'id' => $block_id,
]);

// Get iPro Property ID for the house
$property_id = get_post_meta( $post->post_parent, 'ipro_property_id', true );

// Pass data to JavaScript
$js_data = [
	'dateParam' => $date_param,
	'weekParam' => $week_param,
	'houseId' => $post->post_parent,
	'houseName' => get_the_title( $post->post_parent ),
	'propertyId' => $property_id,
	'ajaxUrl' => admin_url( 'admin-ajax.php' ),
	'nonce' => wp_create_nonce( 'house_booking_nonce' ),
	'blockId' => $block_id,
];

$script_handle = 'kate-toms-core-house-booking-flow-view-script';
$js_var_name = 'bookingFlowData_' . str_replace('-', '_', $block_id);

// Pass data to JavaScript
wp_localize_script( $script_handle, $js_var_name, $js_data );

// Debug output (remove in production)
if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
	echo "<!-- Debug: Booking flow block -->\n";
	echo "<!-- Debug: Date param: $date_param -->\n";
	echo "<!-- Debug: Week param: $week_param -->\n";
	echo "<!-- Debug: House ID: {$post->post_parent} -->\n";
	echo "<!-- Debug: Block ID: $block_id -->\n";
}
?>

<div <?php echo $wrapper_attributes; ?>>
	<div class="booking-loading">
		<div class="loading-spinner">
			<div class="spinner"></div>
			<p><?php esc_html_e( 'Loading booking options...', 'kate-toms-core' ); ?></p>
		</div>
	</div>
	
	<div class="booking-error" style="display: none;">
		<h3><?php esc_html_e( 'Booking Error', 'kate-toms-core' ); ?></h3>
		<p class="error-message"></p>
		<p><a href="<?php echo esc_url( get_permalink( $post->post_parent ) ); ?>"><?php esc_html_e( '← Return to house page', 'kate-toms-core' ); ?></a></p>
	</div>
	
	<div class="booking-container" style="display: none;">
		<!-- Booking content will be populated by JavaScript -->
	</div>
</div>
