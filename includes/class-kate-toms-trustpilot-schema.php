<?php
/**
 * Brand-level Trustpilot aggregateRating.
 *
 * Attaches an AggregateRating to the sitewide Organization node using the
 * TrustScore and review count from Trustpilot's public Business Units API.
 *
 * The rating is attached to Yoast's existing `#organization` node rather than
 * printed as a second script. Yoast already emits one JSON-LD graph per page
 * carrying that entity, and a standalone Organization script would create a
 * competing description of the same thing — the exact problem raised when this
 * work was first scoped. Where Yoast is unavailable, nothing is emitted: there
 * is no Organization to attach to, and inventing one would reintroduce the
 * duplicate entity.
 *
 * Values are never fetched during a page request. A cron event refreshes the
 * cache, and a cold cache emits no rating at all — a page with no stars is a
 * far smaller problem than a page whose markup disagrees with the widget, or a
 * render blocked on a third-party API.
 *
 * @package Kate_Toms_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Publishes the brand-level Trustpilot rating.
 */
class Kate_Toms_Trustpilot_Schema {

	/**
	 * Trustpilot business unit for kateandtoms.com.
	 *
	 * Matches the `data-businessunit-id` the widgets render with. If the two
	 * ever diverge the markup would describe a different business than the
	 * widget on the page.
	 *
	 * @var string
	 */
	const BUSINESS_UNIT_ID = '5cd41de1c4dd7a0001be3a14';

	/**
	 * Fragment identifying the sitewide Organization entity.
	 *
	 * Hardcoded rather than read from Yoast's Schema_IDs class so a rename
	 * inside Yoast cannot fatal the site. Mirrors Kate_Toms_House_Schema.
	 *
	 * @var string
	 */
	const ORGANIZATION_HASH = '#organization';

	/**
	 * Transient holding the fetched rating.
	 *
	 * @var string
	 */
	const CACHE_KEY = 'kt_trustpilot_rating';

	/**
	 * How long a fetched rating stays usable.
	 *
	 * Longer than the refresh interval so a single failed refresh does not
	 * blank the stars — the previous figures keep serving until the next run
	 * succeeds. They drift by a handful of reviews at most in that window.
	 *
	 * @var int
	 */
	const CACHE_TTL = 3 * DAY_IN_SECONDS;

	/**
	 * Cron hook that refreshes the cache.
	 *
	 * @var string
	 */
	const CRON_HOOK = 'kate_toms_core_refresh_trustpilot_rating';

	/**
	 * Wire up the hooks.
	 */
	public function __construct() {
		add_filter( 'wpseo_schema_graph', array( $this, 'add_rating_to_graph' ), 11 );
		add_action( self::CRON_HOOK, array( $this, 'refresh' ) );
		add_action( 'init', array( $this, 'maybe_schedule' ) );
	}

	/**
	 * Ensure the refresh event is scheduled.
	 */
	public function maybe_schedule() {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'twicedaily', self::CRON_HOOK );
		}
	}

	/**
	 * Attach aggregateRating to the Organization node.
	 *
	 * @param array $graph Yoast's schema graph.
	 * @return array The graph, with the rating attached where possible.
	 */
	public function add_rating_to_graph( $graph ) {
		if ( ! is_array( $graph ) || ! $this->has_trustpilot_widget() ) {
			return $graph;
		}

		$rating = $this->get_rating();

		if ( empty( $rating ) ) {
			return $graph;
		}

		foreach ( $graph as $index => $node ) {
			if ( ! is_array( $node ) || empty( $node['@id'] ) ) {
				continue;
			}

			if ( ! $this->is_organization_node( $node ) ) {
				continue;
			}

			// Never overwrite a rating another integration already published.
			if ( isset( $graph[ $index ]['aggregateRating'] ) ) {
				break;
			}

			$graph[ $index ]['aggregateRating'] = array(
				'@type'       => 'AggregateRating',
				'ratingValue' => $rating['ratingValue'],
				'ratingCount' => $rating['ratingCount'],
				'bestRating'  => 5,
				'worstRating' => 1,
			);

			break;
		}

		return $graph;
	}

	/**
	 * Is this graph node the sitewide Organization?
	 *
	 * @param array $node A schema graph node.
	 * @return bool
	 */
	private function is_organization_node( array $node ) {
		if ( substr( (string) $node['@id'], -strlen( self::ORGANIZATION_HASH ) ) !== self::ORGANIZATION_HASH ) {
			return false;
		}

		$type = isset( $node['@type'] ) ? $node['@type'] : '';

		// Yoast may type the node as a subclass, or as a list of types.
		return in_array( 'Organization', (array) $type, true ) || is_string( $type );
	}

	/**
	 * Is a Trustpilot widget rendered on the current page?
	 *
	 * The brief only permits the rating where the widget is visible. In
	 * practice the footer template part carries one, so this is true sitewide
	 * — but checking rather than assuming means the markup disappears on its
	 * own if the footer widget is ever removed, instead of silently claiming a
	 * rating no visitor can see.
	 *
	 * @return bool
	 */
	private function has_trustpilot_widget() {
		static $found = null;

		if ( null !== $found ) {
			return $found;
		}

		$found = false;

		$post = get_post();

		if ( $post instanceof WP_Post && has_block( 'kate-toms-core/kateandtoms-trustpilot', $post ) ) {
			$found = true;
			return $found;
		}

		foreach ( $this->get_template_part_contents() as $content ) {
			if ( has_block( 'kate-toms-core/kateandtoms-trustpilot', $content ) ) {
				$found = true;
				break;
			}
		}

		return $found;
	}

	/**
	 * Content of the template parts that render on every page.
	 *
	 * Resolved through get_block_template() so a part customised in the Site
	 * Editor (stored in the database) is read instead of the theme file it
	 * overrides — the footer here is exactly that case.
	 *
	 * @return string[]
	 */
	private function get_template_part_contents() {
		$contents = array();

		if ( ! function_exists( 'get_block_template' ) ) {
			return $contents;
		}

		$stylesheet = get_stylesheet();

		/**
		 * Filters the template parts checked for a Trustpilot widget.
		 *
		 * @param string[] $slugs Template part slugs.
		 */
		$slugs = apply_filters( 'kate_toms_core_trustpilot_template_parts', array( 'footer' ) );

		foreach ( (array) $slugs as $slug ) {
			$template = get_block_template( $stylesheet . '//' . $slug, 'wp_template_part' );

			if ( $template && ! empty( $template->content ) ) {
				$contents[] = $template->content;
			}
		}

		return $contents;
	}

	/**
	 * The cached rating, if one has been fetched.
	 *
	 * @return array|null array{ratingValue: float, ratingCount: int}, or null.
	 */
	public function get_rating() {
		$cached = get_transient( self::CACHE_KEY );

		return is_array( $cached ) ? $cached : null;
	}

	/**
	 * Fetch the current rating and cache it.
	 *
	 * @return array|WP_Error The stored rating, or an error.
	 */
	public function refresh() {
		$key = $this->get_api_key();

		if ( '' === $key ) {
			return new WP_Error(
				'kate_toms_trustpilot_no_key',
				'TRUSTPILOT_API_KEY is not defined.'
			);
		}

		$url = sprintf(
			'https://api.trustpilot.com/v1/business-units/%s?apikey=%s',
			rawurlencode( self::BUSINESS_UNIT_ID ),
			rawurlencode( $key )
		);

		$response = wp_remote_get( $url, array( 'timeout' => 15 ) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );

		if ( 200 !== (int) $code ) {
			return new WP_Error(
				'kate_toms_trustpilot_http_error',
				sprintf( 'Trustpilot API returned HTTP %d.', (int) $code )
			);
		}

		$rating = Kate_Toms_Trustpilot_Rating::from_payload( json_decode( wp_remote_retrieve_body( $response ), true ) );

		if ( null === $rating ) {
			return new WP_Error(
				'kate_toms_trustpilot_bad_payload',
				'Trustpilot API response did not contain a usable score.'
			);
		}

		set_transient( self::CACHE_KEY, $rating, self::CACHE_TTL );

		return $rating;
	}

	/**
	 * The Trustpilot API key.
	 *
	 * Read from wp-config, with a filter so an environment can supply it
	 * another way. Mirrors how the YouTube key is resolved in
	 * Kate_Toms_House_Schema.
	 *
	 * Only the public Business Units endpoint is used, which authenticates
	 * with the API key alone — TRUSTPILOT_SECRET_KEY is not needed here.
	 *
	 * @return string The key, empty when unset.
	 */
	private function get_api_key() {
		$key = defined( 'TRUSTPILOT_API_KEY' ) ? (string) TRUSTPILOT_API_KEY : '';

		/**
		 * Filters the Trustpilot API key used for the brand rating.
		 *
		 * @param string $key The key, empty when unset.
		 */
		return trim( (string) apply_filters( 'kate_toms_core_trustpilot_api_key', $key ) );
	}
}
