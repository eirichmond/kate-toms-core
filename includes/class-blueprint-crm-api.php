<?php
/**
 * CRM API client for Kate & Tom's booking system.
 *
 * Provides OAuth2 authentication and authenticated HTTP request handling
 * for the Kate & Tom's property booking API at booking.kateandtoms.com.
 *
 * @package    Kate_Toms_Core
 * @subpackage Kate_Toms_Core/includes
 */

declare(strict_types=1);

/**
 * CRM API client for the Kate & Tom's booking system.
 *
 * Handles OAuth2 token management (client credentials flow, transient cache)
 * and authenticated GET requests to the booking API. Uses the WordPress HTTP
 * API (wp_remote_post / wp_remote_get) rather than raw cURL.
 *
 * Business-specific methods (e.g. search_houses) are added as public methods
 * on this class. The existing House_Calendar_Manager is left untouched.
 */
class Kate_Toms_Blueprint_CRM_API {

	/**
	 * Base URL for all booking API endpoints.
	 *
	 * @var string
	 */
	private string $api_base_url = 'https://booking.kateandtoms.com/apis';

	/**
	 * OAuth2 token endpoint URL.
	 *
	 * @var string
	 */
	private string $oauth_token_url = 'https://booking.kateandtoms.com/oauth/2.0/token';

	/**
	 * OAuth2 Basic auth header value (Base64-encoded client credentials).
	 *
	 * @var string
	 */
	private string $oauth_auth_header = 'Basic MTAwMTo2NDNmNjc5NTA1NTA0ZDE3OTY1NDQ2NDdkMTFjNWIwMA==';

	/**
	 * WordPress transient key used to cache the access token.
	 *
	 * Uses a distinct key from House_Calendar_Manager to avoid collisions.
	 *
	 * @var string
	 */
	private string $token_transient_key = 'kt_blueprint_crm_api_token';

	/**
	 * Returns a valid access token, refreshing from the API if necessary.
	 *
	 * Checks the transient cache first. If the cached value is missing or
	 * empty, fetches a new one via the OAuth2 client credentials flow.
	 *
	 * @return string|false Access token string on success, false on failure.
	 */
	public function get_access_token(): string|false {
		$cached = get_transient( $this->token_transient_key );

		if ( false !== $cached && '' !== $cached ) {
			return (string) $cached;
		}

		return $this->refresh_access_token();
	}

	/**
	 * Transient key for caching the full properties list.
	 *
	 * @var string
	 */
	private string $properties_transient_key = 'kt_blueprint_crm_properties';

	/**
	 * Cache duration for the full properties list (24 hours).
	 *
	 * @var int
	 */
	private int $properties_cache_duration = 24 * HOUR_IN_SECONDS;

	/**
	 * Makes an authenticated GET request to a CRM API endpoint.
	 *
	 * Passes the access token as an `access_token` query parameter, matching
	 * the convention used by the Kate & Tom's booking API.
	 *
	 * @param string $endpoint Relative endpoint path, e.g. '/properties'.
	 * @param array  $args     Optional additional query parameters.
	 * @param int    $timeout  Request timeout in seconds (default 30).
	 *
	 * @return array|WP_Error Decoded JSON array on success, WP_Error on failure.
	 */
	public function request( string $endpoint, array $args = array(), int $timeout = 30 ): array|WP_Error {
		$token = $this->get_access_token();

		if ( false === $token ) {
			return new WP_Error(
				'kt_crm_auth_failed',
				__( 'Failed to obtain CRM API access token.', 'kate-toms-core' )
			);
		}

		$url = add_query_arg(
			array_merge( array( 'access_token' => $token ), $args ),
			$this->api_base_url . $endpoint
		);

		$response = wp_remote_get(
			$url,
			array(
				'headers' => array( 'Accept' => 'application/json' ),
				'timeout' => $timeout,
			)
		);

		return $this->handle_response( $response, $endpoint );
	}

	/**
	 * Searches the CRM properties list by name, returning matched houses.
	 *
	 * Fetches the full properties list on first call and caches it for 24 hours.
	 * Subsequent calls filter the local cache instantly. Only returns active
	 * (non-suspended, non-withdrawn, non-hidden) properties.
	 *
	 * @param string $query Case-insensitive substring to match against property Name.
	 *
	 * @return array Array of [ 'crm_id' => int, 'crm_title' => string ] items.
	 */
	public function search_houses( string $query ): array {
		$properties = $this->get_all_properties();

		if ( empty( $query ) || empty( $properties ) ) {
			return array();
		}

		$query_lower = strtolower( $query );

		$matched = array_filter(
			$properties,
			static function ( array $property ) use ( $query_lower ): bool {
				$name = strtolower( (string) ( $property['Name'] ?? '' ) );
				return str_contains( $name, $query_lower );
			}
		);

		return array_values(
			array_map(
				static function ( array $property ): array {
					return array(
						'crm_id'    => (int) $property['Id'],
						'crm_title' => (string) $property['Name'],
					);
				},
				$matched
			)
		);
	}

	/**
	 * Returns the full CRM properties list, using a 24-hour transient cache.
	 *
	 * The initial fetch is slow (~60s) due to response size. After that, results
	 * are served from the transient cache until it expires.
	 *
	 * @return array Array of raw property objects from the CRM, or empty on failure.
	 */
	private function get_all_properties(): array {
		$cached = get_transient( $this->properties_transient_key );

		if ( false !== $cached && is_array( $cached ) ) {
			return $cached;
		}

		$result = $this->request( '/properties', array(), 90 );

		if ( is_wp_error( $result ) || empty( $result ) ) {
			$this->log_error( 'Failed to fetch properties list: ' . ( is_wp_error( $result ) ? $result->get_error_message() : 'empty response' ) );
			return array();
		}

		$active = array_values(
			array_filter(
				$result,
				static function ( array $p ): bool {
					return empty( $p['Suspended'] ) && empty( $p['Withdrawn'] ) && empty( $p['HideOnWebsite'] );
				}
			)
		);

		set_transient( $this->properties_transient_key, $active, $this->properties_cache_duration );

		return $active;
	}

	/**
	 * Fetches a fresh OAuth2 access token via the client credentials grant.
	 *
	 * On success, stores the token in a transient with an expiry matching
	 * the API response minus a 5-minute buffer to avoid using expired tokens.
	 *
	 * @return string|false Access token on success, false on failure.
	 */
	private function refresh_access_token(): string|false {
		$response = wp_remote_post(
			$this->oauth_token_url,
			array(
				'body'    => 'grant_type=client_credentials',
				'headers' => array(
					'Authorization' => $this->oauth_auth_header,
					'Content-Type'  => 'application/x-www-form-urlencoded',
				),
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			$this->log_error( 'Token refresh request error: ' . $response->get_error_message() );
			return false;
		}

		$http_code = wp_remote_retrieve_response_code( $response );

		if ( 200 !== $http_code ) {
			$this->log_error( sprintf( 'Token refresh failed. HTTP %d: %s', $http_code, wp_remote_retrieve_body( $response ) ) );
			return false;
		}

		return $this->parse_and_cache_token( wp_remote_retrieve_body( $response ) );
	}

	/**
	 * Parses a token response body and stores the token in a transient.
	 *
	 * @param string $body Raw JSON response body from the token endpoint.
	 *
	 * @return string|false Access token on success, false if parsing fails.
	 */
	private function parse_and_cache_token( string $body ): string|false {
		$data = json_decode( $body, true );

		if ( JSON_ERROR_NONE !== json_last_error() || ! isset( $data['access_token'] ) ) {
			$this->log_error( 'Token parse error: ' . json_last_error_msg() );
			return false;
		}

		$token      = (string) $data['access_token'];
		$expires_in = isset( $data['expires_in'] ) ? (int) $data['expires_in'] : 3600;
		$ttl        = max( MINUTE_IN_SECONDS, $expires_in - 300 );

		set_transient( $this->token_transient_key, $token, $ttl );

		return $token;
	}

	/**
	 * Processes a wp_remote_get/post response into an array or WP_Error.
	 *
	 * @param array|WP_Error $response  Response from wp_remote_get/post.
	 * @param string         $endpoint  Endpoint label used in error messages.
	 *
	 * @return array|WP_Error Decoded JSON array on success, WP_Error on failure.
	 */
	private function handle_response( array|WP_Error $response, string $endpoint ): array|WP_Error {
		if ( is_wp_error( $response ) ) {
			$this->log_error( sprintf( 'Request error on %s: %s', $endpoint, $response->get_error_message() ) );
			return $response;
		}

		$http_code = wp_remote_retrieve_response_code( $response );

		if ( $http_code < 200 || $http_code >= 300 ) {
			$this->log_error( sprintf( 'HTTP %d on %s: %s', $http_code, $endpoint, wp_remote_retrieve_body( $response ) ) );
			return new WP_Error( 'kt_crm_http_error', sprintf( 'HTTP %d', $http_code ) );
		}

		return $this->parse_json_body( wp_remote_retrieve_body( $response ), $endpoint );
	}

	/**
	 * Decodes a JSON string and returns the result as an array.
	 *
	 * @param string $body     Raw JSON string from the API response body.
	 * @param string $endpoint Endpoint label used in error messages.
	 *
	 * @return array|WP_Error Decoded array on success, WP_Error if JSON is invalid.
	 */
	private function parse_json_body( string $body, string $endpoint ): array|WP_Error {
		$data = json_decode( $body, true );

		if ( JSON_ERROR_NONE !== json_last_error() ) {
			$this->log_error( sprintf( 'JSON parse error on %s: %s', $endpoint, json_last_error_msg() ) );
			return new WP_Error( 'kt_crm_parse_error', json_last_error_msg() );
		}

		return is_array( $data ) ? $data : array();
	}

	/**
	 * Writes an error message to the PHP error log when WP_DEBUG is active.
	 *
	 * @param string $message Error message to log.
	 *
	 * @return void
	 */
	private function log_error( string $message ): void {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( '[Kate & Toms Blueprint CRM API] ' . $message );
		}
	}
}
