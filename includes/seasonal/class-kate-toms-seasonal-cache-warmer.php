<?php
/**
 * Seasonal / availability landing page cache warmer.
 *
 * Recomputes the house list behind every seasonal and availability landing
 * page section and stores it via Kate_Toms_Seasonal_Results_Cache. Lives
 * outside the WP CLI command so the admin cache screen (#505) can run the
 * same warm from a background WP-Cron job; `wp kt-seasonal-cache warm` is a
 * thin wrapper around it.
 *
 * This is the only place allowed to fetch a cold calendar from the booking
 * API: doing it per house during a page request is what made these pages hang
 * and return 504s. Callers must check the booking API is up first, so an
 * outage doesn't replace good lists with empty ones.
 *
 * @package Kate_Toms_Core
 */

if ( ! class_exists( 'Kate_Toms_Seasonal_Cache_Warmer' ) ) {

	/**
	 * Warms the seasonal/availability landing page house lists.
	 */
	class Kate_Toms_Seasonal_Cache_Warmer {

		/**
		 * Landing page blocks whose results are cached, and the post types they
		 * appear on.
		 *
		 * @var array<string, string>
		 */
		public const BLOCKS = array(
			'kate-toms-core/house-seasonal-landing-pages' => 'seasonal',
			'kate-toms-core/house-availability-landing-pages' => 'availability',
		);

		/**
		 * Recompute the house list for every seasonal and availability section.
		 *
		 * @param int           $post_id Optional single post to warm; 0 for all.
		 * @param callable|null $log     Optional callback, invoked per section with
		 *                               ( $post_id, $criteria, $house_ids ).
		 * @return array{posts: int, sections: int, houses: int} Summary.
		 */
		public static function warm( $post_id = 0, $log = null ) {
			$post_id = (int) $post_id;

			$posts = get_posts(
				array(
					'post_type'      => array( 'seasonal', 'availability' ),
					'post_status'    => 'publish',
					'posts_per_page' => -1,
					'post__in'       => $post_id ? array( $post_id ) : array(),
					'fields'         => 'ids',
				)
			);

			$sections = 0;
			$houses   = 0;

			foreach ( $posts as $id ) {
				foreach ( self::find_landing_blocks( $id ) as $block ) {
					$criteria = self::criteria_for( $id, $block );

					if ( ! $criteria ) {
						continue;
					}

					$house_ids = self::compute( $criteria );

					Kate_Toms_Seasonal_Results_Cache::set( $criteria, $house_ids );

					++$sections;
					$houses += count( $house_ids );

					if ( is_callable( $log ) ) {
						call_user_func( $log, $id, $criteria, $house_ids );
					}
				}
			}

			return array(
				'posts'    => count( $posts ),
				'sections' => $sections,
				'houses'   => $houses,
			);
		}

		/**
		 * Find the landing page blocks on a post.
		 *
		 * @param int $post_id Post ID.
		 * @return array<int, array> Parsed blocks.
		 */
		public static function find_landing_blocks( $post_id ) {
			$post = get_post( $post_id );

			if ( ! $post ) {
				return array();
			}

			$found = array();
			$walk  = static function ( $blocks ) use ( &$walk, &$found ) {
				foreach ( $blocks as $block ) {
					if ( isset( self::BLOCKS[ $block['blockName'] ?? '' ] ) ) {
						$found[] = $block;
					}

					if ( ! empty( $block['innerBlocks'] ) ) {
						$walk( $block['innerBlocks'] );
					}
				}
			};

			$walk( parse_blocks( $post->post_content ) );

			return $found;
		}

		/**
		 * Rebuild the criteria a landing block renders with.
		 *
		 * Mirrors the two render.php templates — same meta, same pattern config,
		 * same defaults — so the key computed here is the one the page will look up.
		 *
		 * @param int   $post_id Post ID.
		 * @param array $block   Parsed block.
		 * @return array|null Criteria, or null when the post is not configured.
		 */
		public static function criteria_for( $post_id, array $block ) {
			$attrs     = $block['attrs'] ?? array();
			$is_offers = 'kate-toms-core/house-availability-landing-pages' === $block['blockName'];

			$periods_to_include = get_post_meta( $post_id, 'periods_to_include', true );

			if ( empty( $periods_to_include ) ) {
				return null;
			}

			if ( $is_offers ) {
				$rolling = get_post_meta( $post_id, 'rolling_upcoming_period', true );

				if ( empty( $rolling ) ) {
					return null;
				}

				$beginning = current_time( 'Y-m-d' );
				$ending    = gmdate( 'Y-m-d', strtotime( $beginning . ' +' . (int) $rolling . ' weeks' ) );
			} else {
				$beginning = get_post_meta( $post_id, 'beginning', true );
				$ending    = get_post_meta( $post_id, 'ending', true );

				if ( empty( $beginning ) || empty( $ending ) ) {
					return null;
				}
			}

			$period_mapping = array(
				'Week'            => 'week',
				'weeks'           => 'week',
				'week'            => 'week',
				'2 night weekend' => '2-night-weekend',
				'3 night weekend' => '3-night-weekend',
				'Midweek'         => 'midweek',
				'5 nights'        => '5-night', // Legacy stored value.
				'5 night'         => '5-night',
			);

			$api_periods = array();

			foreach ( (array) $periods_to_include as $period ) {
				if ( isset( $period_mapping[ $period ] ) ) {
					$api_periods[] = $period_mapping[ $period ];
				}
			}

			if ( empty( $api_periods ) ) {
				return null;
			}

			$location_slugs = array(
				'coast'     => 'sea',
				'cotswolds' => 'cotswolds',
				'country'   => 'country',
				'town'      => 'town',
			);

			$pattern_style = $attrs['patternStyle'] ?? 'coast';
			$location      = $location_slugs[ $pattern_style ] ?? 'sea';

			return array(
				'location'    => $location,
				'beginning'   => $beginning,
				'ending'      => $ending,
				// The offers-only variant tags its key, matching the render.
				'periods'     => $is_offers ? array_merge( $api_periods, array( 'offers' ) ) : $api_periods,
				'orderby'     => $attrs['orderBy'] ?? 'meta_value_num',
				'order'       => $attrs['order'] ?? 'desc',
				'meta_key'    => $attrs['metaKey'] ?? 'sleeps_max', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Criteria array, not a query.
				// Not part of the key; used to drive the computation below.
				'api_periods' => $api_periods,
				'offers_only' => $is_offers,
			);
		}

		/**
		 * Compute the house IDs for one section, fetching cold calendars as needed.
		 *
		 * @param array $criteria Criteria from criteria_for().
		 * @return int[] Ordered house IDs.
		 */
		public static function compute( array $criteria ) {
			$houses = get_posts(
				array(
					'post_type'      => 'houses',
					'post_status'    => 'publish',
					'posts_per_page' => -1,
					'orderby'        => $criteria['orderby'],
					'order'          => $criteria['order'],
					'meta_key'       => $criteria['meta_key'], // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Mirrors the block render's ordering.
					'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Mirrors the block render's ordering.
						array(
							'key'     => $criteria['meta_key'],
							'compare' => 'EXISTS',
							'type'    => 'NUMERIC',
						),
					),
					'tax_query'      => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- Mirrors the block render's region filter.
						array(
							'taxonomy' => 'location',
							'field'    => 'slug',
							'terms'    => $criteria['location'],
						),
					),
				)
			);

			if ( empty( $houses ) ) {
				return array();
			}

			$filtered = kate_toms_filter_houses_by_seasonal_availability(
				$houses,
				$criteria['beginning'],
				$criteria['ending'],
				$criteria['api_periods'],
				true
			);

			if ( $criteria['offers_only'] ) {
				$filtered = array_values(
					array_filter(
						$filtered,
						function ( $house ) use ( $criteria ) {
							return kate_toms_house_has_seasonal_offer(
								$house->ID,
								$criteria['beginning'],
								$criteria['ending'],
								$criteria['api_periods']
							);
						}
					)
				);
			}

			return array_map(
				function ( $house ) {
					return (int) $house->ID;
				},
				$filtered
			);
		}
	}
}
