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
					'page'    => array(
						'type'              => 'integer',
						'default'           => 1,
						'sanitize_callback' => 'absint',
					),
					'per_page' => array(
						'type'              => 'integer',
						'default'           => 20,
						'sanitize_callback' => 'absint',
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

		// Register seasonal houses endpoint for infinite scroll (ID-based pagination).
		register_rest_route(
			'kate-toms/v1',
			'/houses-seasonal-load',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_seasonal_houses_by_ids' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'house_ids'      => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
					'pattern_style'  => array(
						'type'              => 'string',
						'default'           => 'coast',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'beginning_date' => array(
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'ending_date'    => array(
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'title_bg_color' => array(
						'type'              => 'string',
						'default'           => 'coloreight',
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
		global $wpdb;
		$params = $request->get_params();

		// Pagination. When a date filter is active we cannot paginate via
		// WP_Query: houses with no qualifying price are dropped after the query
		// runs (see the pricing loop below), so max_num_pages would not match
		// what is actually rendered. In that case we fall back to returning all
		// matches in a single page (hasMore = false) — same as the original
		// behaviour. The no-date case paginates normally.
		$page         = max( 1, (int) ( $request->get_param( 'page' ) ?? 1 ) );
		$per_page     = max( 1, (int) ( $request->get_param( 'per_page' ) ?? 20 ) );
		$is_paginated = empty( $params['date'] );

		// Build query args.
		// Order by sleeps_max (largest first) via a named meta_query clause so it
		// coexists with the size filter's sleeps_* clauses below. Mirrors the
		// kate-toms-core/house-load-search block; houses without sleeps_max are
		// excluded by the EXISTS clause.
		$args = array(
			'post_type'      => 'houses',
			'posts_per_page' => $is_paginated ? $per_page : -1,
			'paged'          => $is_paginated ? $page : 1,
			'post_status'    => 'publish',
			'orderby'        => array( 'sleeps_max_clause' => 'DESC' ),
		);

		// Add taxonomy queries.
		$tax_query = array();

		// Handle location filtering with default location.
		$location_terms = array();
		if ( ! empty( $params['local'] ) || ! empty( $params['default_location'] ) ) {
			
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

		// Add meta queries. The named sleeps_max clause is what `orderby` above
		// sorts on, and its EXISTS compare excludes houses with no sleeps_max.
		$meta_query = array(
			'sleeps_max_clause' => array(
				'key'     => 'sleeps_max',
				'compare' => 'EXISTS',
				'type'    => 'NUMERIC',
			),
		);

		// Size filter: precompute matching post IDs via raw SQL so we can use
		// COALESCE to mirror the old theme's fallback logic exactly:
		//   effective_min = sleeps_min if set, otherwise sleeps_max
		// A house is included when sleeps_max >= range_min (can fit at least
		// the smallest group) AND effective_min <= range_max (doesn't require
		// more people than the largest group in the band).
		$size_post_ids = null;
		if ( ! empty( $params['size'] ) ) {
			$size_ranges = array(
				'2'  => array( 2, 10 ),
				'10' => array( 10, 20 ),
				'20' => array( 20, 999 ),
			);

			if ( isset( $size_ranges[ $params['size'] ] ) ) {
				$range         = $size_ranges[ $params['size'] ];
				$size_post_ids = $wpdb->get_col(
					$wpdb->prepare(
						"SELECT DISTINCT p.ID
						FROM {$wpdb->posts} p
						INNER JOIN {$wpdb->postmeta} pm_max
							ON ( p.ID = pm_max.post_id AND pm_max.meta_key = 'sleeps_max' )
						LEFT JOIN {$wpdb->postmeta} pm_min
							ON ( p.ID = pm_min.post_id AND pm_min.meta_key = 'sleeps_min' )
						WHERE p.post_type = 'houses'
						AND p.post_status = 'publish'
						AND CAST( pm_max.meta_value AS SIGNED ) >= %d
						AND COALESCE( NULLIF( CAST( pm_min.meta_value AS SIGNED ), 0 ), CAST( pm_max.meta_value AS SIGNED ) ) <= %d",
						$range[0],
						$range[1]
					)
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

		// Merge size and date post ID constraints. If both filters are active,
		// post__in must be their intersection; otherwise whichever is set wins.
		if ( null !== $size_post_ids ) {
			$size_ids = ! empty( $size_post_ids ) ? array_map( 'intval', $size_post_ids ) : array( 0 );
			if ( isset( $args['post__in'] ) ) {
				$intersection        = array_intersect( $args['post__in'], $size_ids );
				$args['post__in']    = ! empty( $intersection ) ? array_values( $intersection ) : array( 0 );
			} else {
				$args['post__in'] = $size_ids;
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

		// Determine whether further pages exist. Always false for the date
		// branch (single un-paginated response) — see note above.
		$has_more = $is_paginated ? ( $page < $query->max_num_pages ) : false;

		// Advert HTML is returned separately and only appended by the client
		// once the final page has loaded (hasMore = false), so adverts never
		// land mid-grid between pages.
		$adverts_html = '';

		// Build the response HTML.
		ob_start();
		$house_count = 0;

		if ( $query->have_posts() ) {

			// Pricing setup: build property ID lookup and checkin date once before the loop.
			$calendar_manager  = null;
			$property_id_map   = array();
			$checkin_date      = null;
			$dtype_period_map  = array(
				'1' => array( '2-night-weekend', '3-night-weekend' ),
				'2' => array( 'week' ),
				'3' => array( 'midweek', '2-night-midweek' ),
			);

			if ( ! empty( $params['date'] ) && class_exists( 'House_Calendar_Manager' ) ) {
				$calendar_manager = new House_Calendar_Manager();
				$checkin_date     = new DateTime( $params['date'] );

				// Build an indexed WP house ID → iPro PropertyId map once.
				$reflection     = new ReflectionClass( $calendar_manager );
				$mapping_method = $reflection->getMethod( 'get_cached_property_mapping' );
				$mapping_method->setAccessible( true );
				$property_mapping = $mapping_method->invoke( $calendar_manager );

				if ( is_array( $property_mapping ) ) {
					foreach ( $property_mapping as $property ) {
						$wp_id   = isset( $property['PropertyReference'] ) ? (int) $property['PropertyReference'] : 0;
						$ipro_id = isset( $property['PropertyId'] ) ? (string) $property['PropertyId'] : '';
						if ( $wp_id && $ipro_id ) {
							$property_id_map[ $wp_id ] = $ipro_id;
						}
					}
				}
			}

			global $kt_current_house_pricing;

			while ( $query->have_posts() ) {
				$query->the_post();
				$house_count++;

				// Fetch pricing for this house when a date is provided.
				$kt_current_house_pricing = array();
				if ( $calendar_manager && $checkin_date ) {
					$house_id    = get_the_ID();
					$property_id = $property_id_map[ $house_id ] ?? '';

					if ( $property_id ) {
						// Only use cached calendar data — never hit the external API
						// during a user request. The cron cache warmer keeps these warm.
						$transient_key = "kt_house_calendar_{$property_id}";
						$calendar_data = get_transient( $transient_key );

						if ( false !== $calendar_data && isset( $calendar_data['availability'] ) ) {
							$kt_current_house_pricing = $calendar_manager->get_booking_periods_for_date(
								$property_id,
								clone $checkin_date
							);
						}

						// Filter by duration type if selected.
						if ( ! empty( $params['dtype'] ) && ! empty( $kt_current_house_pricing ) ) {
							if ( ! empty( $kt_current_house_pricing['no_breaks'] ) ) {
								// get_booking_periods_for_date() returns a
								// { no_breaks, message } sentinel (not a list of
								// periods) when the property has no bookable breaks
								// for the chosen arrival day. Such a house cannot
								// satisfy a duration-type filter, so drop it.
								$kt_current_house_pricing = array();
							} else {
								$allowed_periods = $dtype_period_map[ $params['dtype'] ] ?? array();
								if ( ! empty( $allowed_periods ) ) {
									$kt_current_house_pricing = array_values(
										array_filter(
											$kt_current_house_pricing,
											function ( $period ) use ( $allowed_periods ) {
												return is_array( $period )
													&& isset( $period['id'] )
													&& in_array( $period['id'], $allowed_periods, true );
											}
										)
									);
								}
							}
						}
					}

				}

				// When a date is selected, skip houses that have no qualifying price.
				if ( $checkin_date && empty( $kt_current_house_pricing ) ) {
					--$house_count;
					continue;
				}

				// Render the house card pattern. We include the file directly
				// (rather than via do_blocks + pattern registry) so that the PHP
				// in each pattern re-evaluates per house — the pattern registry
				// caches evaluated content after the first call.
				$pattern_file = '';
				if ( in_array( 604, $location_terms ) ) {
					$pattern_file = 'house-card-search-cotswolds';
				} elseif ( in_array( 810, $location_terms ) ) {
					$pattern_file = 'house-card-search-coast';
				} elseif ( in_array( 790, $location_terms ) ) {
					$pattern_file = 'house-card-search-country';
				} elseif ( in_array( 603, $location_terms ) ) {
					$pattern_file = 'house-card-search-town';
				}

				if ( $pattern_file ) {
					ob_start();
					include get_theme_file_path( "patterns/{$pattern_file}.php" );
					echo do_blocks( ob_get_clean() );
				}

				// Reset pricing global after each house.
				$kt_current_house_pricing = array();
			}

			// Check if we need adverts to fill the row, but only once the final
			// page has been reached. The row is filled against the grand total
			// (found_posts) when paginating, or the rendered count for the
			// single-response date branch.
			$advert_basis = $is_paginated ? (int) $query->found_posts : $house_count;
			$remainder    = $advert_basis % 4;
			if ( ! $has_more && $remainder > 0 ) {
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

					// Capture advert placeholders into their own buffer.
					ob_start();
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
					$adverts_html = ob_get_clean();
				}
			}
		} else {
			echo '<div class="houses-filter__error"><p>No houses found</p></div>';
		}

		wp_reset_postdata();

		$response = array(
			'html'    => ob_get_clean(),
			'total'   => $house_count,
			'hasMore' => $has_more,
			'adverts' => $adverts_html,
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
				// AND: when multiple location terms are set (e.g. a broad region
				// plus a granular location), a house must match all of them.
				'operator' => 'AND',
			);
		}

		if ( ! empty( $feature_term_ids ) ) {
			$tax_query[] = array(
				'taxonomy' => 'feature',
				'field'    => 'term_id',
				'terms'    => $feature_term_ids,
				'operator' => 'IN',
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
						<div class="house-card advert-placeholder">
							<div class="house-card__image">
								<img src="<?php echo esc_url( $advert['image_url'] ); ?>"
									alt="Advertisement"
									style="width: 100%; height: 100%; object-fit: cover;">
							</div>
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
	/**
	 * Get seasonal houses by IDs for infinite scroll.
	 *
	 * Accepts pre-filtered house IDs (from initial render) and returns
	 * rendered card HTML with seasonal pricing. This avoids re-running
	 * expensive availability checks on every scroll.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response The response with rendered HTML.
	 */
	public function get_seasonal_houses_by_ids( $request ) {
		$house_ids_param = $request->get_param( 'house_ids' );
		$pattern_style   = $request->get_param( 'pattern_style' ) ?? 'coast';
		$beginning_date  = $request->get_param( 'beginning_date' ) ?? '';
		$ending_date     = $request->get_param( 'ending_date' ) ?? '';
		$title_bg_color  = $request->get_param( 'title_bg_color' ) ?? 'coloreight';

		// Parse comma-separated house IDs.
		$house_ids = array_filter( array_map( 'absint', explode( ',', $house_ids_param ) ) );

		if ( empty( $house_ids ) ) {
			return wp_send_json_success(
				array(
					'html'    => '',
					'adverts' => '',
					'hasMore' => false,
				)
			);
		}

		// Fetch posts by IDs, preserving the order from the request.
		$query = new WP_Query(
			array(
				'post_type'      => 'houses',
				'post_status'    => 'publish',
				'post__in'       => $house_ids,
				'orderby'        => 'post__in',
				'posts_per_page' => count( $house_ids ),
			)
		);

		// All period keys for seasonal pricing display.
		$all_period_keys = array( 'week', '2-night-weekend', '3-night-weekend', 'midweek' );

		// Build the response HTML.
		ob_start();

		if ( $query->have_posts() ) {
			while ( $query->have_posts() ) {
				$query->the_post();
				$house_id = get_the_ID();
				?>
				<!-- House Card -->
				<div class="wp-block-group has-white-background-color has-background house-card" style="min-height:365px">
					<!-- Featured Image -->
					<?php if ( has_post_thumbnail() ) : ?>
						<a href="<?php the_permalink(); ?>">
							<?php the_post_thumbnail( 'house_search', array( 'style' => 'width: 100%; height: auto; display: block;' ) ); ?>
						</a>
					<?php endif; ?>

					<!-- Post Title with styling from pattern -->
					<h2 class="wp-block-heading has-text-align-center has-small-font-size has-white-color has-<?php echo esc_attr( $title_bg_color ); ?>-background-color"
					   style="margin-top:0;margin-bottom:0;padding-top:var(--wp--preset--spacing--40);padding-right:var(--wp--preset--spacing--40);padding-bottom:var(--wp--preset--spacing--40);padding-left:var(--wp--preset--spacing--40);font-style:normal;font-weight:600;font-size:var(--wp--preset--font-size--small)">
						<a href="<?php the_permalink(); ?>" style="color: var(--wp--preset--color--white); text-decoration: none;">
							<?php the_title(); ?>
						</a>
					</h2>

					<!-- Brief Description -->
					<div class="wp-block-group" style="padding-top:var(--wp--preset--spacing--30);padding-right:var(--wp--preset--spacing--30);padding-bottom:var(--wp--preset--spacing--30);padding-left:var(--wp--preset--spacing--30)">
						<?php
						$brief_description = get_post_meta( $house_id, 'brief_description', true );
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
								$sleeps_min = get_post_meta( $house_id, 'sleeps_min', true );
								$sleeps_max = get_post_meta( $house_id, 'sleeps_max', true );
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
								$location_text = get_post_meta( $house_id, 'location_text', true );
								if ( $location_text ) :
									?>
									<p class="has-text-align-right has-x-small-font-size" style="margin: 0;"><?php echo esc_html( $location_text ); ?></p>
								<?php endif; ?>
							</div>
						</div>
					</div>

					<!-- Seasonal Prices -->
					<?php
					if ( ! empty( $beginning_date ) && ! empty( $ending_date ) ) :
						$seasonal_prices = kate_toms_get_seasonal_prices( $house_id, $beginning_date, $ending_date, $all_period_keys );
						if ( ! empty( $seasonal_prices ) ) :
							?>
							<div class="wp-block-group house_meta seasp" style="border-top-color:var(--wp--preset--color--tertiary);border-top-width:1px;padding-top:var(--wp--preset--spacing--30);padding-right:var(--wp--preset--spacing--30);padding-bottom:var(--wp--preset--spacing--30);padding-left:var(--wp--preset--spacing--30)">
								<?php
								$all_prices_with_from = get_post_meta( $house_id, 'all_prices_with_from', true );

								foreach ( $seasonal_prices as $period_label => $rates ) :
									// Skip unavailable periods (indicated by -2).
									if ( in_array( '-2', $rates, true ) ) {
										continue;
									}

									// Pluralize period names if multiple rates.
									$display_period = $period_label;
									if ( count( $rates ) > 1 ) {
										$display_period = $period_label . 's';
										$display_period = str_replace( 'ss', 's', $display_period );
									}

									// Check if any rates have "from" indicator (+).
									$has_from_indicator = false;
									foreach ( $rates as $rate ) {
										if ( strstr( $rate, '+' ) ) {
											$has_from_indicator = true;
											break;
										}
									}

									// Clean rates (strip +, *, spaces).
									$clean_rates = array_map(
										function ( $rate ) {
											return str_replace( array( '+', '*', ' ' ), '', $rate );
										},
										$rates
									);

									// Get minimum price.
									$min_price = kate_toms_convert_from_price( $clean_rates );

									// Determine if we show "from" or exact price.
									$show_from = ( $all_prices_with_from || count( $rates ) > 1 || $has_from_indicator );
									?>
									<p class="has-x-small-font-size" style="margin: 0;">
										<?php echo esc_html( ucfirst( $display_period ) ); ?>
										<?php echo $show_from ? ' from ' : ' - '; ?>
										&pound;<?php echo esc_html( $min_price ); ?>
									</p>
								<?php endforeach; ?>
							</div>
						<?php endif; ?>
					<?php endif; ?>
				</div>
				<?php
			}
		}

		$html = ob_get_clean();

		// Generate adverts HTML (the client tells us this is the last batch by context).
		// We always return adverts so the client can use them when hasMore becomes false.
		$adverts_html = '';
		$location_key = $pattern_style;

		// We don't know the total from here, but the client does.
		// Return adverts for the location so the client can insert them on the last page.
		if ( class_exists( 'Kate_Toms_Core_Admin' ) ) {
			// Calculate adverts for up to 3 remaining grid slots (max possible remainder for 4-col grid).
			$admin   = new Kate_Toms_Core_Admin( 'kate-toms-core', '1.0.0' );
			$adverts = $admin->get_adverts_for_location( $location_key, 3 );

			if ( ! empty( $adverts ) ) {
				ob_start();
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
				$adverts_html = ob_get_clean();
			}
		}

		wp_reset_postdata();

		$response = array(
			'html'    => $html,
			'adverts' => $adverts_html,
			'hasMore' => true, // Client determines this from its own context.
		);

		return wp_send_json_success( $response );
	}

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

