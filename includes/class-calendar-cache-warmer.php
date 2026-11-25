<?php
/**
 * Calendar Cache Warmer — WP CLI Commands
 *
 * Provides two WP CLI commands to keep calendar/pricing transient caches warm
 * so that filtered search results never hit the external booking API during
 * a user request.
 *
 * Usage (server cron):
 *   # Full warm — run once overnight (takes ~15 min for ~400 properties)
 *   wp kt-cache warm
 *
 *   # Incremental update — run every 20 min via server cron
 *   wp kt-cache update
 *
 * @package Kate_Toms_Core
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

class Calendar_Cache_CLI_Command extends WP_CLI_Command {

	/**
	 * iPro API base URL for properties endpoints.
	 *
	 * @var string
	 */
	private $properties_api_url = 'https://booking.kateandtoms.com/apis/properties';

	/**
	 * Full warm: fetch and cache calendar data for ALL published houses.
	 *
	 * Run once overnight via server cron. Takes ~15 minutes for ~400 properties.
	 *
	 * ## EXAMPLES
	 *
	 *     # Run a full cache warm
	 *     wp kt-cache warm
	 *
	 * @subcommand warm
	 *
	 * @param array $args       Positional arguments (unused).
	 * @param array $assoc_args Associative arguments (unused).
	 */
	public function warm( $args, $assoc_args ) {
		if ( ! class_exists( 'House_Calendar_Manager' ) ) {
			WP_CLI::error( 'House_Calendar_Manager class not found.' );
		}

		// Shares the admin screen's lock (#505), so a click can't start a second warm alongside this one.
		if ( ! Kate_Toms_Cache_Manager::begin_cli( 'calendar' ) ) {
			WP_CLI::warning( 'A calendar refresh started from Settings → House Caches is already running; skipping.' );
			return;
		}

		$calendar_manager = new House_Calendar_Manager();

		// One cheap call up front rather than ~800 failing ones if iPro is down.
		$api = $calendar_manager->check_api_available();

		if ( is_wp_error( $api ) ) {
			Kate_Toms_Cache_Manager::finish( 'calendar', false, 'Booking API unavailable, existing cache left in place: ' . $api->get_error_message(), 'cli' );
			WP_CLI::error( 'Booking API unavailable, existing calendars left in place: ' . $api->get_error_message() );
		}

		$wp_to_property = Kate_Toms_Cache_Manager::get_calendar_targets( $calendar_manager );

		if ( false === $wp_to_property ) {
			Kate_Toms_Cache_Manager::finish( 'calendar', false, 'Could not load the iPro property mapping; existing calendars left in place.', 'cli' );
			WP_CLI::error( 'Failed to fetch property mapping.' );
		}

		if ( empty( $wp_to_property ) ) {
			Kate_Toms_Cache_Manager::finish( 'calendar', false, 'No published houses with an iPro property.', 'cli' );
			WP_CLI::warning( 'No published houses found with booking properties.' );
			return;
		}

		$total  = count( $wp_to_property );
		$warmed = 0;
		$failed = 0;

		$progress = \WP_CLI\Utils\make_progress_bar( "Warming $total houses", $total );

		foreach ( $wp_to_property as $property_id ) {
			// api_get() inside handles the token, including a refresh on 401.
			$result = $calendar_manager->get_calendar_data( $property_id, '', true );

			if ( is_array( $result ) && ! isset( $result['error'] ) ) {
				++$warmed;
			} else {
				++$failed;
			}

			$progress->tick();
		}

		$progress->finish();

		Kate_Toms_Cache_Manager::finish(
			'calendar',
			$warmed > 0,
			sprintf( '%d of %d houses refreshed, %d failed (kept their previous data).', $warmed, $total, $failed ),
			'cli'
		);

		WP_CLI::success(
			sprintf(
				'Full warm complete: %d warmed, %d failed (of %d houses).',
				$warmed,
				$failed,
				$total
			)
		);

		wp_mail( 'elliott@squareonemd.co.uk', 'Full warm complete', 'Full warm complete: ' . $warmed . ' properties warmed, ' . $failed . ' properties failed to warm (of ' . $total . ' houses).' );
	}

	/**
	 * Incremental update: refresh only properties whose rates changed recently.
	 *
	 * Uses the iPro /apis/properties/lastupdated endpoint. Run every 20 min
	 * via server cron.
	 *
	 * ## OPTIONS
	 *
	 * [--minutes=<minutes>]
	 * : How many minutes back to check for updates. Defaults to 25 (20 min interval + 5 min buffer).
	 *
	 * ## EXAMPLES
	 *
	 *     # Check last 25 minutes (default)
	 *     wp kt-cache update
	 *
	 *     # Check last 60 minutes
	 *     wp kt-cache update --minutes=60
	 *
	 * @subcommand update
	 */
	public function update( $args, $assoc_args ) {
		if ( ! class_exists( 'House_Calendar_Manager' ) ) {
			WP_CLI::error( 'House_Calendar_Manager class not found.' );
		}

		$minutes = (int) ( $assoc_args['minutes'] ?? 25 );

		$calendar_manager = new House_Calendar_Manager();
		$access_token     = $this->get_access_token( $calendar_manager );

		if ( empty( $access_token ) ) {
			WP_CLI::error( 'Failed to obtain API access token.' );
		}

		// Fetch properties updated in the last N minutes.
		$updated_properties = $this->fetch_last_updated( $calendar_manager, $minutes );

		if ( false === $updated_properties ) {
			WP_CLI::error( 'Failed to fetch last updated properties from iPro API.' );
		}

		// Filter to only properties with rate changes.
		$rate_changed_ids = array();
		foreach ( $updated_properties as $property ) {
			if (
				isset( $property['PropertyId'], $property['Details']['Rates'] ) &&
				'Yes' === $property['Details']['Rates']
			) {
				$rate_changed_ids[] = (string) $property['PropertyId'];
			}
		}

		if ( empty( $rate_changed_ids ) ) {
			WP_CLI::success( sprintf(
				'Checked last %d min — no rate changes found.',
				$minutes
			) );
			return;
		}

		$warmed = 0;
		$failed = 0;

		foreach ( $rate_changed_ids as $property_id ) {
			$result = $calendar_manager->get_calendar_data( $property_id, $access_token, true );

			if ( is_array( $result ) && ! isset( $result['error'] ) ) {
				++$warmed;
			} else {
				++$failed;
			}
		}

		WP_CLI::success( sprintf(
			'Incremental update: checked last %d min, %d properties changed rates, warmed %d, failed %d.',
			$minutes,
			count( $rate_changed_ids ),
			$warmed,
			$failed
		) );

		wp_mail( 'elliott@squareonemd.co.uk', 'Incremental update', 'Incremental update: checked last ' . $minutes . ' min, ' . count( $rate_changed_ids ) . ' properties changed rates, warmed ' . $warmed . ' properties, failed to warm ' . $failed . ' properties.' );
	}

	/**
	 * Fetch properties that have been updated in the last N minutes.
	 *
	 * Goes through House_Calendar_Manager::api_get() so an expired token is
	 * replaced and the request retried once.
	 *
	 * @param House_Calendar_Manager $calendar_manager Calendar manager instance.
	 * @param int                    $minutes          Number of minutes to look back.
	 * @return array|false Array of updated properties or false on failure.
	 */
	private function fetch_last_updated( $calendar_manager, $minutes ) {
		$url = sprintf( '%s/lastupdated?lastUpdated=%d', $this->properties_api_url, $minutes );

		$data = $calendar_manager->api_get( $url );

		if ( is_wp_error( $data ) ) {
			WP_CLI::warning( 'lastupdated request failed: ' . $data->get_error_message() );
			return false;
		}

		return $data;
	}

	/**
	 * Get the API access token from the calendar manager.
	 *
	 * @param House_Calendar_Manager $calendar_manager Calendar manager instance.
	 * @return string Access token or empty string on failure.
	 */
	private function get_access_token( $calendar_manager ) {
		$reflection     = new ReflectionClass( $calendar_manager );
		$token_property = $reflection->getProperty( 'api_access_token' );
		$token_property->setAccessible( true );
		return $token_property->getValue( $calendar_manager );
	}
}

WP_CLI::add_command( 'kt-cache', 'Calendar_Cache_CLI_Command' );
