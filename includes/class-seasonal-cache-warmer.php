<?php
/**
 * Seasonal / Availability Landing Page Cache Warmer — WP CLI Command
 *
 * Precomputes the house list behind every seasonal and availability landing
 * page section, so a page view is a single transient read instead of a
 * calendar check per house.
 *
 * This is the only place allowed to fetch a cold calendar from the booking API:
 * doing it per house during a page request is what made these pages hang and
 * return 504s.
 *
 * Run it after the calendar warm, whose per-property calendars it reuses.
 *
 * Usage (server cron):
 *   # Nightly, after `wp kt-cache warm`.
 *   wp kt-seasonal-cache warm
 *
 * @package Kate_Toms_Core
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

/**
 * WP CLI command that warms the seasonal/availability landing page house lists.
 */
class Seasonal_Cache_CLI_Command extends WP_CLI_Command {

	/**
	 * Landing page blocks whose results are cached, and the post types they
	 * appear on.
	 *
	 * @var array<string, string>
	 */
	private $blocks = array(
		'kate-toms-core/house-seasonal-landing-pages'     => 'seasonal',
		'kate-toms-core/house-availability-landing-pages' => 'availability',
	);

	/**
	 * Recompute the house list for every seasonal and availability section.
	 *
	 * ## OPTIONS
	 *
	 * [--post_id=<id>]
	 * : Warm a single seasonal/availability post rather than all of them.
	 *
	 * ## EXAMPLES
	 *
	 *     # Warm every seasonal and availability landing page
	 *     wp kt-seasonal-cache warm
	 *
	 *     # Warm one page
	 *     wp kt-seasonal-cache warm --post_id=101639
	 *
	 * @subcommand warm
	 *
	 * @param array $args       Positional arguments (unused).
	 * @param array $assoc_args Associative arguments.
	 */
	public function warm( $args, $assoc_args ) {
		if ( ! class_exists( 'Kate_Toms_Seasonal_Results_Cache' ) ) {
			WP_CLI::error( 'Kate_Toms_Seasonal_Results_Cache class not found.' );
		}

		$single = isset( $assoc_args['post_id'] ) ? (int) $assoc_args['post_id'] : 0;

		$posts = get_posts(
			array(
				'post_type'      => array( 'seasonal', 'availability' ),
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'post__in'       => $single ? array( $single ) : array(),
				'fields'         => 'ids',
			)
		);

		if ( empty( $posts ) ) {
			WP_CLI::warning( 'No seasonal or availability posts found.' );
			return;
		}

		$sections = 0;
		$houses   = 0;

		foreach ( $posts as $post_id ) {
			foreach ( $this->find_landing_blocks( $post_id ) as $block ) {
				$criteria = $this->criteria_for( $post_id, $block );

				if ( ! $criteria ) {
					continue;
				}

				$house_ids = $this->compute( $criteria );

				Kate_Toms_Seasonal_Results_Cache::set( $criteria, $house_ids );

				++$sections;
				$houses += count( $house_ids );

				WP_CLI::log(
					sprintf(
						'%s [%s %s→%s] %d houses',
						get_the_title( $post_id ),
						$criteria['location'],
						$criteria['beginning'],
						$criteria['ending'],
						count( $house_ids )
					)
				);
			}
		}

		WP_CLI::success(
			sprintf(
				'Seasonal cache warmed: %d post(s), %d section(s), %d house slots.',
				count( $posts ),
				$sections,
				$houses
			)
		);
	}

	/**
	 * Find the landing page blocks on a post.
	 *
	 * @param int $post_id Post ID.
	 * @return array<int, array> Parsed blocks.
	 */
	private function find_landing_blocks( $post_id ) {
		$post = get_post( $post_id );

		if ( ! $post ) {
			return array();
		}

		$found = array();
		$walk  = function ( $blocks ) use ( &$walk, &$found ) {
			foreach ( $blocks as $block ) {
				if ( isset( $this->blocks[ $block['blockName'] ?? '' ] ) ) {
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
	private function criteria_for( $post_id, array $block ) {
		$attrs   = $block['attrs'] ?? array();
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
			'5 night'         => 'week',
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
			'location'     => $location,
			'beginning'    => $beginning,
			'ending'       => $ending,
			// The offers-only variant tags its key, matching the render.
			'periods'      => $is_offers ? array_merge( $api_periods, array( 'offers' ) ) : $api_periods,
			'orderby'      => $attrs['orderBy'] ?? 'meta_value_num',
			'order'        => $attrs['order'] ?? 'desc',
			'meta_key'     => $attrs['metaKey'] ?? 'sleeps_max',
			// Not part of the key; used to drive the computation below.
			'api_periods'  => $api_periods,
			'offers_only'  => $is_offers,
		);
	}

	/**
	 * Compute the house IDs for one section, fetching cold calendars as needed.
	 *
	 * @param array $criteria Criteria from criteria_for().
	 * @return int[] Ordered house IDs.
	 */
	private function compute( array $criteria ) {
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
				'tax_query'      => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Mirrors the block render's region filter.
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

WP_CLI::add_command( 'kt-seasonal-cache', 'Seasonal_Cache_CLI_Command' );
