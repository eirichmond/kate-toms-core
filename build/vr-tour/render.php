<?php
/**
 * Server-side rendering for the vr-tour block.
 *
 * Outputs a responsive Matterport iframe embed following Matterport's
 * recommended implementation (allowfullscreen + xr-spatial-tracking).
 *
 * @package Kate_Toms_Core
 *
 * @var array $attributes Block attributes.
 */

$kt_vr_tour_raw = isset( $attributes['tourUrl'] ) ? trim( $attributes['tourUrl'] ) : '';

if ( '' === $kt_vr_tour_raw ) {
	return;
}

// Accept a bare Matterport model ID as well as a full share link.
if ( ! preg_match( '#^https?://#i', $kt_vr_tour_raw ) && preg_match( '#^[a-zA-Z0-9]+$#', $kt_vr_tour_raw ) ) {
	$kt_vr_tour_raw = 'https://my.matterport.com/show/?m=' . $kt_vr_tour_raw;
}

$kt_vr_tour_url = esc_url( $kt_vr_tour_raw );

if ( '' === $kt_vr_tour_url ) {
	return;
}

$kt_vr_tour_anchor = ! empty( $attributes['anchor'] ) ? $attributes['anchor'] : 'vr-tour';
$kt_vr_tour_title  = ! empty( $attributes['tourTitle'] ) ? $attributes['tourTitle'] : __( 'VR Tour', 'kate-toms-core' );

$kt_vr_tour_wrapper_attributes = get_block_wrapper_attributes(
	array(
		'id' => $kt_vr_tour_anchor,
	)
);
?>
<div <?php echo $kt_vr_tour_wrapper_attributes; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by get_block_wrapper_attributes(). ?>>
	<div class="kt-vr-tour__frame">
		<iframe
			src="<?php echo esc_url( $kt_vr_tour_url ); ?>"
			title="<?php echo esc_attr( $kt_vr_tour_title ); ?>"
			allow="fullscreen; web-share; xr-spatial-tracking"
			allowfullscreen
			loading="lazy"
		></iframe>
	</div>
</div>
