<?php
/**
 * House Calendar Availability Block Template
 *
 * @package Kate_Toms_Core
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$house_id = $attributes['houseId'] ?? '';
$months_to_show = $attributes['monthsToShow'] ?? 6;
$show_rates = $attributes['showRates'] ?? true;
$auto_refresh = $attributes['autoRefresh'] ?? false;
$refresh_interval = $attributes['refreshInterval'] ?? 20;

// Don't render if no house ID is provided
if ( empty( $house_id ) ) {
	return;
}

// Generate unique ID for this block instance
$block_id = 'house-calendar-' . wp_generate_uuid4();

$wrapper_attributes = get_block_wrapper_attributes([
	'class' => 'house-calendar-availability',
	'id' => $block_id,
]);

// WordPress automatically handles the viewScript enqueue based on block.json
// The script handle is auto-generated as: kate-toms-core-house-calendar-availability-view-script
$script_handle = 'kate-toms-core-house-calendar-availability-view-script';

$js_data = [
	'houseId' => $house_id,
	'monthsToShow' => $months_to_show,
	'showRates' => $show_rates,
	'autoRefresh' => $auto_refresh,
	'refreshInterval' => $refresh_interval,
	'ajaxUrl' => admin_url( 'admin-ajax.php' ),
	'nonce' => wp_create_nonce( 'calendar_data_nonce' ),
	'blockId' => $block_id,
];

$js_var_name = 'houseCalendarData_' . str_replace('-', '_', $block_id);

// Pass data to JavaScript
wp_localize_script( $script_handle, $js_var_name, $js_data );

// Debug output (remove in production)
if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
	echo "<!-- Debug: Script handle: $script_handle -->\n";
	echo "<!-- Debug: JS var name: $js_var_name -->\n";
	echo "<!-- Debug: House ID: $house_id -->\n";
	echo "<!-- Debug: Block ID: $block_id -->\n";
}
?>

<div <?php echo $wrapper_attributes; ?>>
	<div class="calendar-loading">
		<div class="loading-spinner"></div>
		<p><?php esc_html_e( 'Loading calendar data...', 'kate-toms-core' ); ?></p>
	</div>
	
	<div class="calendar-error" style="display: none;">
		<p><?php esc_html_e( 'Failed to load calendar data. Please try again later.', 'kate-toms-core' ); ?></p>
		<button class="retry-button"><?php esc_html_e( 'Retry', 'kate-toms-core' ); ?></button>
	</div>
	
	<div class="calendar-container" style="display: none;">
		<!-- Calendar content will be populated by JavaScript -->
	</div>
</div>
