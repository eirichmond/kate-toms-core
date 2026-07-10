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
	 */
	public function warm( $args, $assoc_args ) {
		$warmed = 0;
		$failed = 0;
		$page   = 1;

		do {
			$url = 1 === $page
				? home_url( '/houses-sitemap.xml' )
				: home_url( '/houses-sitemap' . $page . '.xml' );

			$response = wp_remote_get(
				$url,
				array(
					'timeout' => 60,
				)
			);

			$code = wp_remote_retrieve_response_code( $response );

			if ( is_wp_error( $response ) || 200 !== $code ) {
				if ( 1 === $page ) {
					++$failed;
					WP_CLI::warning( 'Failed to warm ' . $url . ( is_wp_error( $response ) ? ': ' . $response->get_error_message() : " (HTTP {$code})" ) );
				}
				break;
			}

			++$warmed;
			++$page;
		} while ( $page <= $this->max_pages );

		WP_CLI::success(
			sprintf(
				'Houses sitemap warm complete: %d page(s) warmed, %d failed.',
				$warmed,
				$failed
			)
		);

		wp_mail(
			'elliott@squareonemd.co.uk',
			'Houses sitemap cache warm complete',
			sprintf( 'Houses sitemap cache warm complete: %d page(s) warmed, %d failed.', $warmed, $failed )
		);
	}
}

WP_CLI::add_command( 'kt-sitemap-cache', 'Sitemap_Cache_CLI_Command' );
