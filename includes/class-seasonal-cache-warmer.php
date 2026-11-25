<?php
/**
 * Seasonal / Availability Landing Page Cache Warmer — WP CLI Command
 *
 * Precomputes the house list behind every seasonal and availability landing
 * page section, so a page view is a single transient read instead of a
 * calendar check per house. The work itself lives in
 * Kate_Toms_Seasonal_Cache_Warmer so the admin cache screen can run it too.
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
		if ( ! class_exists( 'Kate_Toms_Seasonal_Cache_Warmer' ) ) {
			WP_CLI::error( 'Kate_Toms_Seasonal_Cache_Warmer class not found.' );
		}

		$single = isset( $assoc_args['post_id'] ) ? (int) $assoc_args['post_id'] : 0;

		// A full warm shares the admin screen's lock and "last refreshed" (#505); a single page doesn't.
		if ( ! $single && ! Kate_Toms_Cache_Manager::begin_cli( 'seasonal' ) ) {
			WP_CLI::warning( 'A seasonal refresh started from Settings → House Caches is already running; skipping.' );
			return;
		}

		// Don't recompute while the booking API is down: every house would read as
		// unavailable and good cached lists would be replaced with empty ones.
		$api = ( new House_Calendar_Manager() )->check_api_available();

		if ( is_wp_error( $api ) ) {
			if ( ! $single ) {
				Kate_Toms_Cache_Manager::finish( 'seasonal', false, 'Booking API unavailable, existing cache left in place: ' . $api->get_error_message(), 'cli' );
			}
			WP_CLI::error( 'Booking API unavailable, existing seasonal cache left in place: ' . $api->get_error_message() );
		}

		$result = Kate_Toms_Seasonal_Cache_Warmer::warm(
			$single,
			static function ( $post_id, $criteria, $house_ids ) {
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
		);

		$summary = sprintf( '%d page(s), %d section(s), %d house slots.', $result['posts'], $result['sections'], $result['houses'] );

		if ( ! $single ) {
			Kate_Toms_Cache_Manager::finish( 'seasonal', true, $summary, 'cli' );
		}

		if ( empty( $result['posts'] ) ) {
			WP_CLI::warning( 'No seasonal or availability posts found.' );
			return;
		}

		WP_CLI::success( 'Seasonal cache warmed: ' . $summary );
	}
}

WP_CLI::add_command( 'kt-seasonal-cache', 'Seasonal_Cache_CLI_Command' );
