<?php
/**
 * Related Houses API Class
 *
 * @package Kate_Toms_Core
 */

/**
 * Class Related_Houses_API
 */
class Related_Houses_API {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register the REST API routes.
	 */
	public function register_routes() {
		register_rest_route(
			'kate-toms/v1',
			'/related-houses/save-to-subpages',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'save_to_subpages' ),
				'permission_callback' => array( $this, 'check_permissions' ),
				'args'                => array(
					'parent_id' => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
					'house1_id' => array(
						'required'          => false,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
						'default'           => 0,
					),
					'house2_id' => array(
						'required'          => false,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
						'default'           => 0,
					),
					'house3_id' => array(
						'required'          => false,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
						'default'           => 0,
					),
					'house4_id' => array(
						'required'          => false,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
						'default'           => 0,
					),
				),
			)
		);
	}

	/**
	 * Check if user has permission to save to subpages.
	 *
	 * @return bool True if user can edit posts, false otherwise.
	 */
	public function check_permissions() {
		return current_user_can( 'edit_posts' );
	}

	/**
	 * Save related houses block to all subpages of a parent house.
	 *
	 * @param WP_REST_Request $request The REST API request.
	 * @return WP_REST_Response The response object.
	 */
	public function save_to_subpages( WP_REST_Request $request ) {
		$parent_id = $request->get_param( 'parent_id' );
		$house_ids = array(
			$request->get_param( 'house1_id' ),
			$request->get_param( 'house2_id' ),
			$request->get_param( 'house3_id' ),
			$request->get_param( 'house4_id' ),
		);

		// Validate parent post exists and is a house
		$parent_post = get_post( $parent_id );
		if ( ! $parent_post || 'houses' !== $parent_post->post_type ) {
			return new WP_REST_Response( 
				array( 'success' => false, 'message' => 'Invalid parent house ID' ), 
				400 
			);
		}

		// Get all subpages of this house
		$subpages = get_posts( array(
			'post_type'      => 'houses',
			'post_parent'    => $parent_id,
			'posts_per_page' => -1,
			'post_status'    => array( 'publish', 'private', 'draft' ),
		) );

		if ( empty( $subpages ) ) {
			return new WP_REST_Response( 
				array( 'success' => false, 'message' => 'No subpages found for this house' ), 
				404 
			);
		}

		// Create the related houses block content
		$block_content = $this->generate_related_houses_block( $house_ids );
		
		$updated_count = 0;
		$errors = array();

		foreach ( $subpages as $subpage ) {
			try {
				// Get current content
				$current_content = $subpage->post_content;
				
				// Remove any existing related-houses blocks
				$content_without_related_houses = $this->remove_related_houses_blocks( $current_content );
				
				// Only add the new block if at least one house is selected
				$filtered_house_ids = array_filter( $house_ids, function( $id ) { return $id > 0; } );
				if ( ! empty( $filtered_house_ids ) ) {
					// Append the new related houses block
					$new_content = $content_without_related_houses . "\n\n" . $block_content;
				} else {
					// If no houses selected, just remove existing blocks
					$new_content = $content_without_related_houses;
				}
				
				// Update the post
				$result = wp_update_post( array(
					'ID'           => $subpage->ID,
					'post_content' => $new_content,
				), true );

				if ( is_wp_error( $result ) ) {
					$errors[] = sprintf( 
						'Failed to update %s: %s', 
						$subpage->post_title, 
						$result->get_error_message() 
					);
				} else {
					$updated_count++;
				}
			} catch ( Exception $e ) {
				$errors[] = sprintf( 
					'Error updating %s: %s', 
					$subpage->post_title, 
					$e->getMessage() 
				);
			}
		}

		// Return response
		if ( $updated_count > 0 ) {
			$message = sprintf( 
				'Successfully updated %d subpages', 
				$updated_count 
			);
			
			if ( ! empty( $errors ) ) {
				$message .= sprintf( ' (%d errors occurred)', count( $errors ) );
			}

			return new WP_REST_Response( array(
				'success'       => true,
				'message'       => $message,
				'updated_count' => $updated_count,
				'errors'        => $errors,
			), 200 );
		} else {
			return new WP_REST_Response( array(
				'success' => false,
				'message' => 'No subpages were updated. Errors: ' . implode( '; ', $errors ),
				'errors'  => $errors,
			), 500 );
		}
	}

	/**
	 * Generate the related houses block content.
	 *
	 * @param array $house_ids Array of house IDs.
	 * @return string The block content.
	 */
	private function generate_related_houses_block( $house_ids ) {
		// Filter out zero values
		$house_ids = array_map( 'absint', $house_ids );
		
		$attributes = array(
			'house1Id' => $house_ids[0] ?? 0,
			'house2Id' => $house_ids[1] ?? 0,
			'house3Id' => $house_ids[2] ?? 0,
			'house4Id' => $house_ids[3] ?? 0,
		);

		$attributes_json = wp_json_encode( $attributes );

		return sprintf( 
			'<!-- wp:kate-toms-core/related-houses %s /-->', 
			$attributes_json 
		);
	}

	/**
	 * Remove existing related-houses blocks from content.
	 *
	 * @param string $content The post content.
	 * @return string The content with related-houses blocks removed.
	 */
	private function remove_related_houses_blocks( $content ) {
		// Remove related-houses blocks using regex
		// This matches both self-closing and full blocks
		$pattern = '/<!-- wp:kate-toms-core\/related-houses[^>]*\/-->|<!-- wp:kate-toms-core\/related-houses[^>]*-->.*?<!-- \/wp:kate-toms-core\/related-houses -->/s';
		$content = preg_replace( $pattern, '', $content );
		
		// Clean up extra whitespace
		$content = preg_replace( '/\n\s*\n\s*\n/', "\n\n", $content );
		$content = trim( $content );
		
		return $content;
	}
}