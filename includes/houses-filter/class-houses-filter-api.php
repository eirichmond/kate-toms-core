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

		// Register paginated houses endpoint for infinite scroll.
		register_rest_route(
			'kate-toms/v1',
			'/houses-load',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_paginated_houses' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'page'     => array(
						'type'              => 'integer',
						'default'           => 1,
						'sanitize_callback' => 'absint',
					),
					'per_page'  => array(
						'type'              => 'integer',
						'default'           => 20,
						'sanitize_callback' => 'absint',
					),
					'locations' => array(
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'features'  => array(
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'sizes'     => array(
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'types'     => array(
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'occasions' => array(
						'type'              => 'string',
						'default'           => '',
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
	 * Get filtered houses based on user-selected filters and default locations.
	 * 
	 * This endpoint is triggered by:
	 * 1. Initial page load with default filters
	 * 2. User interaction with filter controls in the frontend (houses-filter/view.js)
	 * 3. Automatic updates when filter values change in the state
	 * 
	 * @param WP_REST_Request $request The request object containing filter parameters:
	 *                                - local: Selected location term ID
	 *                                - default_location: Default location term ID
	 *                                - feature: Selected feature term ID
	 *                                - date: Selected date (YYYY-MM-DD)
	 *                                - dtype: Date type filter
	 *                                - size: Size filter
	 * @return WP_REST_Response The response object containing filtered houses
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
			
			// Add user-selected location if present
			if ( ! empty( $params['local'] ) ) {
				$location_terms[] = $params['local'];
			}
			
			// Add default location if present
			if ( ! empty( $params['default_location'] ) ) {
				$location_terms[] = $params['default_location'];
			}

			// If we have location terms, add them to the tax query
			if ( ! empty( $location_terms ) ) {
				// Create separate tax query clause for each location term
				foreach ($location_terms as $term_id) {
					$tax_query[] = array(
						'taxonomy' => 'location',
						'field'    => 'term_id',
						'terms'    => array($term_id),
						'operator' => 'IN'
					);
				}
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
			$house_count = 0;
			while ( $query->have_posts() ) {
				$query->the_post();
				$house_count++;

				// TODO: Pricing lookup disabled due to performance concerns
				// Each house requires API call to booking system, causing timeouts
				// Consider implementing client-side pricing fetch or caching strategy

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

			// Check if we need adverts to fill the row
			$remainder = $house_count % 4;
			if ( $remainder > 0 ) {
				$adverts_needed = 4 - $remainder;

				// Determine location key for adverts
				$location_key = '';
				if ( in_array( 604, $location_terms ) ) {
					$location_key = 'cotswolds';
				} elseif ( in_array( 810, $location_terms ) ) {
					$location_key = 'coast';
				} elseif ( in_array( 790, $location_terms ) ) {
					$location_key = 'country';
				} elseif ( in_array( 603, $location_terms ) ) {
					$location_key = 'town';
				}

				// Get adverts if we have a location key
				if ( ! empty( $location_key ) && class_exists( 'Kate_Toms_Core_Admin' ) ) {
					$admin = new Kate_Toms_Core_Admin( 'kate-toms-core', '1.0.0' );
					$adverts = $admin->get_adverts_for_location( $location_key, $adverts_needed );

					// Output advert placeholders
					foreach ( $adverts as $advert ) {
						?>
						<!-- Advert Placeholder Card -->
						<div class="house-card advert-placeholder">
							<div class="house-card__image">
								<img src="<?php echo esc_url( $advert['image_url'] ); ?>"
									alt="Advertisement"
									style="width: 100%; height: 100%; object-fit: cover;">
							</div>
						</div>
						<?php
					}
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

	/**
	 * Get paginated houses for infinite scroll loading.
	 *
	 * Returns houses ordered by sleeps_max descending with pagination support.
	 * Used by the house-load-search block for infinite scroll functionality.
	 *
	 * @param WP_REST_Request $request The request object containing:
	 *                                - page: Current page number (default 1)
	 *                                - per_page: Houses per page (default 20).
	 * @return WP_REST_Response The response containing HTML and pagination info.
	 */
	public function get_paginated_houses( $request ) {
		$page            = $request->get_param( 'page' ) ?? 1;
		$per_page        = $request->get_param( 'per_page' ) ?? 20;
		$locations_param = $request->get_param( 'locations' ) ?? '';
		$features_param  = $request->get_param( 'features' ) ?? '';
		$sizes_param     = $request->get_param( 'sizes' ) ?? '';
		$types_param     = $request->get_param( 'types' ) ?? '';
		$occasions_param = $request->get_param( 'occasions' ) ?? '';

		// Helper function to parse comma-separated IDs.
		$parse_ids = function ( $param ) {
			if ( empty( $param ) ) {
				return array();
			}
			return array_filter( array_map( 'absint', explode( ',', $param ) ) );
		};

		$location_term_ids = $parse_ids( $locations_param );
		$feature_term_ids  = $parse_ids( $features_param );
		$size_term_ids     = $parse_ids( $sizes_param );
		$type_term_ids     = $parse_ids( $types_param );
		$occasion_term_ids = $parse_ids( $occasions_param );

		// Map location term IDs to title background colors.
		$location_color_map = array(
			604 => 'colorfive',       // Cotswolds.
			810 => 'coloreight',      // Coast.
			790 => 'titlecolorthree', // Country.
			603 => 'coloreight',      // Town.
		);

		// Map location term IDs to advert location keys.
		$location_key_map = array(
			604 => 'cotswolds',
			810 => 'coast',
			790 => 'country',
			603 => 'town',
		);

		// Determine title color and location key from first selected location, or use defaults.
		$first_location_id = ! empty( $location_term_ids ) ? $location_term_ids[0] : 0;
		$title_bg_color    = isset( $location_color_map[ $first_location_id ] ) ? $location_color_map[ $first_location_id ] : 'colorfive';
		$location_key      = isset( $location_key_map[ $first_location_id ] ) ? $location_key_map[ $first_location_id ] : 'cotswolds';

		// Build query arguments.
		$args = array(
			'post_type'      => 'houses',
			'post_status'    => 'publish',
			'posts_per_page' => $per_page,
			'paged'          => $page,
			'orderby'        => 'meta_value_num',
			'order'          => 'DESC',
			'meta_key'       => 'sleeps_max',
			'meta_query'     => array(
				array(
					'key'     => 'sleeps_max',
					'compare' => 'EXISTS',
					'type'    => 'NUMERIC',
				),
			),
		);

		// Build taxonomy query for location and/or features.
		$tax_query = array();

		if ( ! empty( $location_term_ids ) ) {
			$tax_query[] = array(
				'taxonomy' => 'location',
				'field'    => 'term_id',
				'terms'    => $location_term_ids,
				'operator' => 'IN',
			);
		}

		if ( ! empty( $feature_term_ids ) ) {
			$tax_query[] = array(
				'taxonomy' => 'feature',
				'field'    => 'term_id',
				'terms'    => $feature_term_ids,
				'operator' => 'AND',
			);
		}

		if ( ! empty( $size_term_ids ) ) {
			$tax_query[] = array(
				'taxonomy' => 'size',
				'field'    => 'term_id',
				'terms'    => $size_term_ids,
				'operator' => 'IN',
			);
		}

		if ( ! empty( $type_term_ids ) ) {
			$tax_query[] = array(
				'taxonomy' => 'type',
				'field'    => 'term_id',
				'terms'    => $type_term_ids,
				'operator' => 'IN',
			);
		}

		if ( ! empty( $occasion_term_ids ) ) {
			$tax_query[] = array(
				'taxonomy' => 'occasion',
				'field'    => 'term_id',
				'terms'    => $occasion_term_ids,
				'operator' => 'IN',
			);
		}

		if ( ! empty( $tax_query ) ) {
			if ( count( $tax_query ) > 1 ) {
				$tax_query['relation'] = 'AND';
			}
			$args['tax_query'] = $tax_query;
		}

		// Execute the query.
		$query        = new WP_Query( $args );
		$total_houses = $query->found_posts;
		$total_pages  = $query->max_num_pages;
		$has_more     = $page < $total_pages;

		// Build the response HTML.
		ob_start();

		if ( $query->have_posts() ) {
			while ( $query->have_posts() ) {
				$query->the_post();
				?>
				<!-- House Card using theme pattern structure -->
				<div class="wp-block-group has-white-background-color has-background house-card" style="min-height:365px">

					<a href="<?php the_permalink(); ?>">
						<!-- Featured Image -->
						<?php if ( has_post_thumbnail() ) : ?>
							<?php the_post_thumbnail( 'house_search', array( 'style' => 'width: 100%; height: auto; display: block;' ) ); ?>
						<?php endif; ?>

						<!-- Post Title with styling from pattern -->
						<h3 class="wp-block-heading has-text-align-center has-small-font-size has-white-color has-<?php echo esc_attr( $title_bg_color ); ?>-background-color"
						style="margin-top:0;margin-bottom:0;padding-top:var(--wp--preset--spacing--40);padding-right:var(--wp--preset--spacing--40);padding-bottom:var(--wp--preset--spacing--40);padding-left:var(--wp--preset--spacing--40);font-style:normal;font-weight:600;font-size:var(--wp--preset--font-size--small)">
							<?php the_title(); ?>
						</h3>

						<!-- Brief Description -->
						<div class="wp-block-group" style="padding-top:var(--wp--preset--spacing--30);padding-right:var(--wp--preset--spacing--30);padding-bottom:var(--wp--preset--spacing--30);padding-left:var(--wp--preset--spacing--30)">
							<?php
							$brief_description = get_post_meta( get_the_ID(), 'brief_description', true );
							if ( $brief_description ) :
								?>
								<p class="has-x-small-font-size"><?php echo esc_html( $brief_description ); ?></p>
							<?php endif; ?>
						</div>

						<!-- Sleeps and Location Info -->
						<div class="wp-block-group" style="border-top-color:var(--wp--preset--color--tertiary);border-top-width:1px;padding-top:var(--wp--preset--spacing--30);padding-right:var(--wp--preset--spacing--30);padding-bottom:var(--wp--preset--spacing--30);padding-left:var(--wp--preset--spacing--30)">
							<div class="wp-block-group" style="display: flex; justify-content: space-between; align-items: center;">
								<!-- Sleeps Info -->
								<div class="wp-block-group" style="display: flex; align-items: center; gap: 0.2em;">
									<?php
									$sleeps_min = get_post_meta( get_the_ID(), 'sleeps_min', true );
									$sleeps_max = get_post_meta( get_the_ID(), 'sleeps_max', true );
									if ( $sleeps_max ) :
										?>
										<p class="has-x-small-font-size" style="margin: 0;">Sleeps </p>
										<?php if ( $sleeps_min ) : ?>
											<p class="has-x-small-font-size" style="margin: 0;"><?php echo esc_html( $sleeps_min ); ?></p>
											<p class="has-x-small-font-size" style="margin: 0;"> to </p>
										<?php endif; ?>
										<p class="has-x-small-font-size" style="margin: 0;"><?php echo esc_html( $sleeps_max ); ?></p>
									<?php endif; ?>
								</div>

								<!-- Location Info -->
								<div class="wp-block-group">
									<?php
									$location_text = get_post_meta( get_the_ID(), 'location_text', true );
									if ( $location_text ) :
										?>
										<p class="has-text-align-right has-x-small-font-size" style="margin: 0;"><?php echo esc_html( $location_text ); ?></p>
									<?php endif; ?>
								</div>
							</div>
						</div>

					</a>
				</div>
				<?php
			}
		}

		$html = ob_get_clean();

		// Generate adverts HTML if this is the last page.
		$adverts_html = '';
		if ( ! $has_more ) {
			// Calculate total houses loaded so far.
			$total_loaded = $total_houses;
			$remainder    = $total_loaded % 4;

			if ( $remainder > 0 ) {
				$adverts_needed = 4 - $remainder;

				// Get adverts for the selected location.
				if ( class_exists( 'Kate_Toms_Core_Admin' ) ) {
					$admin   = new Kate_Toms_Core_Admin( 'kate-toms-core', '1.0.0' );
					$adverts = $admin->get_adverts_for_location( $location_key, $adverts_needed );

					ob_start();
					foreach ( $adverts as $advert ) {
						?>
						<!-- Advert Placeholder Card -->
						<div class="wp-block-group has-white-background-color has-background advert-placeholder" style="min-height:365px">
							<img src="<?php echo esc_url( $advert['image_url'] ); ?>"
								style="width: 100%; height: 368px; display: block; object-fit: cover;"
								alt="Advertisement">
						</div>
						<?php
					}
					$adverts_html = ob_get_clean();
				}
			}
		}

		wp_reset_postdata();

		$response = array(
			'html'        => $html,
			'adverts'     => $adverts_html,
			'page'        => $page,
			'totalPages'  => $total_pages,
			'totalHouses' => $total_houses,
			'hasMore'     => $has_more,
		);

		return wp_send_json_success( $response );
	}

	/**
	 * Get pricing periods for a house on a specific date.
	 *
	 * @param int                     $house_id WordPress house post ID.
	 * @param DateTime                $checkin_date Checkin date.
	 * @param House_Calendar_Manager  $calendar_manager Calendar manager instance.
	 * @return array Pricing periods array.
	 */
	private function get_house_pricing_for_date( $house_id, $checkin_date, $calendar_manager ) {
		try {
			// Use reflection to access private methods
			$reflection = new ReflectionClass( $calendar_manager );

			// Get property ID from house post ID
			$get_property_id_method = $reflection->getMethod( 'get_property_id_from_wp_house_id' );
			$get_property_id_method->setAccessible( true );
			$property_id = $get_property_id_method->invoke( $calendar_manager, $house_id );

			if ( ! $property_id ) {
				return array();
			}

			// Get booking periods for this date
			$get_periods_method = $reflection->getMethod( 'get_booking_periods_for_date' );
			$get_periods_method->setAccessible( true );
			$periods = $get_periods_method->invoke( $calendar_manager, $property_id, $checkin_date );

			return $periods;
		} catch ( Exception $e ) {
			error_log( 'Error getting house pricing: ' . $e->getMessage() );
			return array();
		}
	}
}

