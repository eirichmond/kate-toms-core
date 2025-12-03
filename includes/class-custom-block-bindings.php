<?php
/**
 * Custom Block Bindings for Kate & Toms Core
 *
 * Registers custom block binding sources for dynamic content display.
 *
 * @link       https://elliottrichmond.co.uk
 * @since      1.0.0
 *
 * @package    Kate_Toms_Core
 * @subpackage Kate_Toms_Core/includes
 */

/**
 * Custom Block Bindings class.
 *
 * Registers block binding sources for conditional meta field display.
 *
 * @since      1.0.0
 * @package    Kate_Toms_Core
 * @subpackage Kate_Toms_Core/includes
 * @author     Elliott Richmond <hello@elliottrichmond.co.uk>
 */
class Kate_Toms_Custom_Block_Bindings {

	/**
	 * Register all block binding sources.
	 *
	 * @since    1.0.0
	 */
	public function register_bindings() {
		add_action( 'init', array( $this, 'register_sleeps_range_binding' ) );
		add_action( 'init', array( $this, 'register_sleeps_to_range_binding' ) );
		add_action( 'init', array( $this, 'register_location_text_binding' ) );
	}

	/**
	 * Register sleeps range binding source.
	 *
	 * Combines sleeps_min and sleeps_max meta fields with conditional logic:
	 * - If both exist: displays "min-max"
	 * - If only max exists: displays "max"
	 * - If neither exists: displays empty string
	 *
	 * @since    1.0.0
	 */
	public function register_sleeps_range_binding() {
		register_block_bindings_source(
			'kateandtoms/sleeps-range',
			array(
				'label'              => __( 'Sleeps Range', 'kate-toms-core' ),
				'get_value_callback' => array( $this, 'get_sleeps_range_value' ),
				'uses_context'       => array( 'postId' ),
			)
		);
	}

	/**
	 * Get sleeps range value callback.
	 *
	 * @since    1.0.0
	 * @param    array $source_args    Source arguments.
	 * @param    object $block_instance Block instance.
	 * @return   string                 Formatted sleeps range value.
	 */
	public function get_sleeps_range_value( $source_args, $block_instance ) {
		$post_id = $block_instance->context['postId'] ?? get_the_ID();

		// Get the parent post ID if this is a child page.
		$post = get_post( $post_id );
		if ( $post && $post->post_parent ) {
			$post_id = $post->post_parent;
		}

		$sleeps_min = get_post_meta( $post_id, 'sleeps_min', true );
		$sleeps_max = get_post_meta( $post_id, 'sleeps_max', true );

		if ( $sleeps_min && $sleeps_max ) {
			return $sleeps_min . '-' . $sleeps_max;
		}

		return $sleeps_max ? $sleeps_max : '';
	}

	/**
	 * Register sleeps to range binding source.
	 *
	 * Combines sleeps_min and sleeps_max meta fields with conditional logic:
	 * - If both exist: displays "min to max"
	 * - If only max exists: displays "max"
	 * - If neither exists: displays empty string
	 *
	 * @since    1.0.0
	 */
	public function register_sleeps_to_range_binding() {
		register_block_bindings_source(
			'kateandtoms/sleeps-to-range',
			array(
				'label'              => __( 'Sleeps To Range', 'kate-toms-core' ),
				'get_value_callback' => array( $this, 'get_sleeps_to_range_value' ),
				'uses_context'       => array( 'postId' ),
			)
		);
	}

	/**
	 * Get sleeps to range value callback.
	 *
	 * @since    1.0.0
	 * @param    array $source_args    Source arguments.
	 * @param    object $block_instance Block instance.
	 * @return   string                 Formatted sleeps to range value.
	 */
	public function get_sleeps_to_range_value( $source_args, $block_instance ) {
		$post_id = $block_instance->context['postId'] ?? get_the_ID();

		// Get the parent post ID if this is a child page.
		$post = get_post( $post_id );
		if ( $post && $post->post_parent ) {
			$post_id = $post->post_parent;
		}

		$sleeps_min = get_post_meta( $post_id, 'sleeps_min', true );
		$sleeps_max = get_post_meta( $post_id, 'sleeps_max', true );

		if ( $sleeps_min && $sleeps_max ) {
			return $sleeps_min . ' to ' . $sleeps_max;
		}

		return $sleeps_max ? $sleeps_max : '';
	}

	/**
	 * Register location text binding source.
	 *
	 * Gets location_text meta field from parent post if current post is a child page.
	 *
	 * @since    1.0.0
	 */
	public function register_location_text_binding() {
		register_block_bindings_source(
			'kateandtoms/location-text',
			array(
				'label'              => __( 'Location Text', 'kate-toms-core' ),
				'get_value_callback' => array( $this, 'get_location_text_value' ),
				'uses_context'       => array( 'postId' ),
			)
		);
	}

	/**
	 * Get location text value callback.
	 *
	 * @since    1.0.0
	 * @param    array  $source_args    Source arguments.
	 * @param    object $block_instance Block instance.
	 * @return   string                 Location text value.
	 */
	public function get_location_text_value( $source_args, $block_instance ) {
		$post_id = $block_instance->context['postId'] ?? get_the_ID();

		// Get the parent post ID if this is a child page.
		$post = get_post( $post_id );
		if ( $post && $post->post_parent ) {
			$post_id = $post->post_parent;
		}

		return get_post_meta( $post_id, 'location_text', true );
	}
}
