<?php
/**
 * Houses Filter API Class
 *
 * @package Kate_Toms_Core
 */

/**
 * Class Houses_Filter_API
 */
class Houses_Filter_API {

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
			'/houses',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_filtered_houses' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'date'    => array(
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'dtype'   => array(
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'size'    => array(
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'local'   => array(
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'feature' => array(
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'default_location' => array(
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);
	}

	/**
	 * Get post IDs that match a specific date's availability.
	 *
	 * @param string $year  The year to check availability for.
	 * @param int    $month The month to check availability for.
	 * @param int    $day   The day to check availability for.
	 * @return array Array of post IDs that are available on the specified date.
	 */
	public function get_matching_post_ids( $year, $month, $day ) {
		global $wpdb;

		// Fetch distinct post IDs where availability-days is not empty (either '' or 'a:0:{}') and contains the specific day.
		$query = $wpdb->prepare(
			"SELECT DISTINCT pm1.post_id, pm1.meta_value FROM {$wpdb->postmeta} pm1
			INNER JOIN {$wpdb->postmeta} pm2 ON pm1.post_id = pm2.post_id
			INNER JOIN {$wpdb->posts} p ON pm1.post_id = p.ID
			WHERE pm1.meta_key LIKE %s 
			AND pm2.meta_key LIKE %s AND pm2.meta_value = %d
			AND p.post_type = %s AND p.post_status = %s",
			'%_availability-days',
			'%_month',
			$month,
			'houses',
			'publish'
		);

		$results           = $wpdb->get_results( $query, ARRAY_A );
		$matching_post_ids = array();

		foreach ( $results as $row ) {
			$post_id           = (int) $row['post_id'];
			$availability_days = maybe_unserialize( $row['meta_value'] );

			// Ensure availability-days is not empty and contains the specific day.
			if ( is_array( $availability_days ) && ! empty( $availability_days ) && in_array( $day, $availability_days ) ) {
				$matching_post_ids[] = $post_id;
			}
		}

		$matching_post_ids = array_unique( $matching_post_ids );

		return $matching_post_ids;
	}

	/**
	 * Get filtered houses.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response The response object.
	 */
	public function get_filtered_houses( $request ) {
		$params = $request->get_params();

		// Build query args.
		$args = array(
			'post_type'      => 'houses',
			'posts_per_page' => -1,
			'post_status'    => 'publish',
		);

		// Add taxonomy queries.
		$tax_query = array();

		// Handle location filtering with default location.
		if ( ! empty( $params['local'] ) || ! empty( $params['default_location'] ) ) {
			$location_terms = array();
			
			// Add user-selected location if present.
			if ( ! empty( $params['local'] ) ) {
				$location_terms[] = $params['local'];
			}
			
			// Add default location if present.
			if ( ! empty( $params['default_location'] ) ) {
				$location_terms[] = $params['default_location'];
			}

			// If we have location terms, add them to the tax query.
			if ( ! empty( $location_terms ) ) {
				$tax_query[] = array(
					'taxonomy' => 'location',
					'field'    => 'term_id',
					'terms'    => $location_terms,
					'operator' => 'AND', // Use AND to require both locations.
				);
			}
		}

		if ( ! empty( $params['feature'] ) ) {
			$tax_query[] = array(
				'taxonomy' => 'feature',
				'field'    => 'term_id',
				'terms'    => $params['feature'],
			);
		}

		if ( ! empty( $tax_query ) ) {
			$args['tax_query'] = array_merge(
				array( 'relation' => 'AND' ),
				$tax_query
			);
		}

		// Add meta queries.
		$meta_query = array();

		if ( ! empty( $params['size'] ) ) {
			$size_ranges = array(
				'2'  => array( 2, 10 ),
				'10' => array( 10, 20 ),
				'20' => array( 20, 999 ),
			);

			if ( isset( $size_ranges[ $params['size'] ] ) ) {
				$range        = $size_ranges[ $params['size'] ];
				$meta_query[] = array(
					'relation' => 'AND',
					array(
						'key'     => 'sleeps_min',
						'value'   => $range[0],
						'type'    => 'NUMERIC',
						'compare' => '>=',
					),
					array(
						'key'     => 'sleeps_max',
						'value'   => $range[1],
						'type'    => 'NUMERIC',
						'compare' => '<=',
					),
				);
			}
		}

		if ( ! empty( $params['date'] ) ) {
			// Extract year, month, and day from the date.
			$date_parts = explode( '-', $params['date'] );
			$year       = $date_parts[0];
			$month      = (int) $date_parts[1]; // Ensure integer format.
			$day        = (int) $date_parts[2]; // Ensure integer format.

			$matching_post_ids = $this->get_matching_post_ids( $year, $month, $day );

			if ( ! empty( $matching_post_ids ) ) {
				$args['post__in'] = $matching_post_ids;
			}
		}

		if ( ! empty( $meta_query ) ) {
			$args['meta_query'] = array_merge(
				array( 'relation' => 'AND' ),
				$meta_query
			);
		}

		// Query posts.
		$query = new WP_Query( $args );

		// Build the response HTML.
		ob_start();

		if ( $query->have_posts() ) {
			while ( $query->have_posts() ) {
				$query->the_post();
				// Use your custom pattern.
				if( in_array( 604, $location_terms ) ) {
					echo do_blocks( '<!-- wp:pattern {"slug":"katomswold/house-card-search-cotswolds"} /-->' );
				} elseif ( in_array( 810, $location_terms )) {
					echo do_blocks( '<!-- wp:pattern {"slug":"katomswold/house-card-search-coast"} /-->' );
				} elseif ( in_array( 790, $location_terms )) {
					echo do_blocks( '<!-- wp:pattern {"slug":"katomswold/house-card-search-country"} /-->' );
				} elseif ( in_array( 603, $location_terms )) {
					echo do_blocks( '<!-- wp:pattern {"slug":"katomswold/house-card-search-town"} /-->' );
				}
			}
		} else {
			echo '<div class="houses-filter__error"><p>No houses found</p></div>';
		}

		wp_reset_postdata();

		$response = array(
			'html'  => ob_get_clean(),
			'total' => $query->found_posts,
		);

		header( 'Content-Type: application/json' );
		return wp_send_json_success( $response );
	}
}

