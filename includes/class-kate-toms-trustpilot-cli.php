<?php
/**
 * WP-CLI command for the cached Trustpilot rating.
 *
 * The cron event keeps the rating current; this is for verifying a deployment
 * or priming a fresh environment without waiting for the schedule.
 *
 * @package Kate_Toms_Core
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

/**
 * Refresh the cached Trustpilot rating on demand.
 *
 * The cron event keeps it current; this is for verifying a deployment or
 * priming a fresh environment without waiting for the schedule.
 */
class Kate_Toms_Trustpilot_CLI_Command extends WP_CLI_Command {

	/**
	 * Fetch the current rating and cache it.
	 *
	 * ## EXAMPLES
	 *
	 *     wp kt-trustpilot refresh
	 *
	 * @subcommand refresh
	 */
	public function refresh() {
		$schema = new Kate_Toms_Trustpilot_Schema();
		$result = $schema->refresh();

		if ( is_wp_error( $result ) ) {
			WP_CLI::error( $result->get_error_message() );
		}

		WP_CLI::success(
			sprintf(
				'Cached Trustpilot rating: %s from %d reviews.',
				$result['ratingValue'],
				$result['ratingCount']
			)
		);
	}
}

WP_CLI::add_command( 'kt-trustpilot', 'Kate_Toms_Trustpilot_CLI_Command' );
