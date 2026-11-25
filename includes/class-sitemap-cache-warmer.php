<?php
/**
 * Houses Sitemap Cache Warmer — WP CLI Command
 *
 * `yoast-sitemaps.php` enables Yoast's transient-based sitemap cache, but a
 * transient only gets (re)populated the moment something actually requests
 * the sitemap. If a house is added/edited and nobody revisits
 * /houses-sitemap.xml before the next crawl, that crawl pays the full
 * regeneration cost again. This command proactively re-requests the houses
 * sitemap so the transient is always warm.
 *
 * Usage (server cron):
 *   # Run nightly to keep the houses sitemap cache warm.
 *   wp kt-sitemap-cache warm
 *
 * @package Kate_Toms_Core
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

class Sitemap_Cache_CLI_Command extends WP_CLI_Command {

	/**
	 * Maximum number of paginated sitemap pages to warm, as a safety cap.
	 *
	 * @var int
	 */
	private $max_pages = 20;

	/**
	 * Warm the houses sitemap cache by re-requesting each of its pages.
	 *
	 * Run nightly via server cron so the Yoast sitemap transient never goes
	 * stale, even if a house is added/edited and nobody revisits the
	 * sitemap themselves.
	 *
	 * ## EXAMPLES
	 *
	 *     # Warm the houses sitemap cache
	 *     wp kt-sitemap-cache warm
	 *
	 * @subcommand warm
	 *
	 * @param array $args       Positional arguments (unused).
	 * @param array $assoc_args Associative arguments (unused).
	 */
	public function warm( $args, $assoc_args ) {
		// Shares the admin screen's lock and "last refreshed" (#505).
		if ( ! Kate_Toms_Cache_Manager::begin_cli( 'sitemap' ) ) {
			WP_CLI::warning( 'A sitemap refresh started from Settings → House Caches is already running; skipping.' );
			return;
		}

		$result = Kate_Toms_Cache_Manager::warm_houses_sitemap( $this->max_pages );

		if ( $result['error'] ) {
			WP_CLI::warning( 'Failed to warm ' . $result['error'] );
		}

		Kate_Toms_Cache_Manager::finish(
			'sitemap',
			0 === $result['failed'],
			$result['failed'] ? 'Rebuilding failed: ' . $result['error'] : sprintf( '%d sitemap page(s) rebuilt.', $result['warmed'] ),
			'cli'
		);

		WP_CLI::success(
			sprintf(
				'Houses sitemap warm complete: %d page(s) warmed, %d failed.',
				$result['warmed'],
				$result['failed']
			)
		);

		wp_mail(
			'elliott@squareonemd.co.uk',
			'Houses sitemap cache warm complete',
			sprintf( 'Houses sitemap cache warm complete: %d page(s) warmed, %d failed.', $result['warmed'], $result['failed'] )
		);
	}
}

WP_CLI::add_command( 'kt-sitemap-cache', 'Sitemap_Cache_CLI_Command' );
