<?php
/**
 * Special Offers Booked-Map Cache Warmer — WP CLI Command
 *
 * Deciding whether a special offer has been booked out costs a calendar lookup
 * per offer, and the offers page carries hundreds of them — far too much to do
 * during a page request on a page that is already slow. This command
 * precomputes every verdict into a single transient, which the grid render and
 * the block editor then read in one hit.
 *
 * Run it after the calendar warm, so the per-property calendar transients it
 * depends on are already populated.
 *
 * Usage (server cron):
 *   # Nightly, after `wp kt-cache warm`.
 *   wp kt-offers-cache warm
 *
 * @package Kate_Toms_Core
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

/**
 * WP CLI command that warms the special offers booked map.
 */
class Special_Offers_Cache_CLI_Command extends WP_CLI_Command {

	/**
	 * Recompute the booked-out map for every special offer on the site.
	 *
	 * Offers whose house has no iPro property mapping, or whose date does not
	 * fall on a valid arrival day, are recorded as unknown and will continue to
	 * be shown — the map only ever hides an offer it can positively prove is
	 * booked.
	 *
	 * ## EXAMPLES
	 *
	 *     # Warm the special offers booked map
	 *     wp kt-offers-cache warm
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

		if ( ! class_exists( 'Kate_Toms_Special_Offer_Availability' ) ) {
			WP_CLI::error( 'Kate_Toms_Special_Offer_Availability class not found.' );
		}

		$offers = Kate_Toms_Special_Offer_Availability::collect_offers();

		if ( empty( $offers ) ) {
			WP_CLI::warning( 'No dated special offers found — nothing to warm.' );
			return;
		}

		$progress = WP_CLI\Utils\make_progress_bar( 'Checking offers', count( $offers ) );

		$result = Kate_Toms_Special_Offer_Availability::build_booked_map(
			static function () use ( $progress ) {
				$progress->tick();
			}
		);

		$progress->finish();

		WP_CLI::success(
			sprintf(
				'Special offers booked map warmed: %d booked, %d bookable, %d unknown (shown).',
				$result['booked'],
				$result['bookable'],
				$result['unknown']
			)
		);
	}
}

WP_CLI::add_command( 'kt-offers-cache', 'Special_Offers_Cache_CLI_Command' );
