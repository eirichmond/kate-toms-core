<?php
/**
 * Availability Notes Block Template
 *
 * @package Kate_Toms_Core
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$house_id = $attributes['houseId'] ?? '';

// Don't render if no house ID is provided
if ( empty( $house_id ) ) {
	return;
}

// Get the availability notes directly from the transient cache
$transient_key = '_transient_kt_house_calendar_' . $house_id;
$calendar_data = get_option( $transient_key );

$availability_notes = '';
if ( $calendar_data && isset( $calendar_data['AvailabilityNotes'] ) ) {
	$availability_notes = $calendar_data['AvailabilityNotes'];
}

// Don't render anything if no notes available
if ( empty( $availability_notes ) ) {
	return;
}

$wrapper_attributes = get_block_wrapper_attributes([
	'class' => 'availability-notes',
]);

// Localize script data for editor AJAX calls
if ( is_admin() ) {
	$script_handle = 'kate-toms-core-availability-notes-editor-script';
	$ajax_data = [
		'ajaxUrl' => admin_url( 'admin-ajax.php' ),
		'nonce' => wp_create_nonce( 'calendar_data_nonce' ),
	];
	wp_localize_script( $script_handle, 'availabilityNotesAjax', $ajax_data );
}

?>

<div <?php echo $wrapper_attributes; ?>>
	<div class="availability-notes-content">
		<?php echo wp_kses_post( $availability_notes ); ?>
	</div>
</div> 
