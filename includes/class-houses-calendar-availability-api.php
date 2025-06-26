<?php 

/**
 * House Calendar Manager with Kate & Tom's specific business logic.
 *
 * Handles availability and rates data from the booking API with
 * Kate & Tom's specific pricing keys and stay duration logic.
 *
 * @since Version 3 digits
 */


class House_Calendar_Manager {
	/**
	 * API base URL for booking system.
	 *
	 * @var string
	 */
	private $api_base_url = 'https://booking.kateandtoms.com/apis/property';

    /**
	 * API access token for booking system.
	 *
	 * @var string
	 */
	private $api_access_token = 'AAIAAJrQJtf4qdUOsmg7PDL8HA98mNZykDNVkm1R6HUSU76SOMzCdub_9YsLGRC0Gr-nuCXxLnlW6VR7abHcDaD9w4Uiy0XXaSH87uPdF_1Y3w76PHmvt6lu7n_R9WHDqQf7qFcWRVp1MbsVhssAkc4HFM8L4pE_w9tKj3pbKqbMw_WtxXcoR62So53M7x57uh2XPVHLY_qZ3ILtDB3VFi1kUBR0zeIOjYs3987MTWGwJHtCFEU2a-0jsj3VSL7pVuGJAHnxbjVD3wFF1Z_vFvD5YZ4EeWuME3SVyva-c7p7II2KR3UWSqI0A3w2oOqdluWCSM3S_fb6CUQKRhVW-cIWtElLvAyrYx6snEkWGYMLv0Rhe6ZZ_2JnUAtgqITZciuYqLuGAfTIewCUzGWileFJV0_mCQ0Ip2Nva1rfNPOFrfR4iW4rRXtDPRcRABxzOl248G0nNDNNs-VCVjYTag0-YCvIN3aFUEPIJwVAn0eq2RI-Qki8B5pN68tFt6C6h-J4g55mGToaEB1Z36YXNlMCV8q2jTYJz5akSMZ3g0OmearC4XXNmdWOjbN9WDa2kWj05GQkILBuRl6Ed6c0wolTd7t9QczD_zZg4qUa7l0V5EvfUKK7MXR3TyQ_sWyQLYaVrrLqRMfnikFduaKhzvmNQJ4tjd-QbRSmKLITW2dLRxqqZAIAAAACAAApZffqcFQvb6G1B8QxHcx5j45a2qHsoV0ZG7tToZbJnVKMJ2sbt06aZYm2s7NU_yWBNaO_a2hHBqWrXK0VBGB5tY3VGA5ioP1XrKKi60ytRxETVpFiRVeWAJ0mUow-IKg6jb57kgQEQwmHggi1cCUB6WbONTJn7Jl6UkyRSerzK43GNUPY0wQ7M1vh9-uvBeb-lYy47J3UdrkBXzoiq1NvEh_wwZbOUdZnRrlaVtX08N4YMbRWVv_ibxHoEdHbpQ8Ma4-P_p866uKwG0D_mYW37z8tW8ZBPdvuPoZAjGUR6gOLItkLWTfZyttbGZfM-iLFTcvg4dNwQiud6mApLQUepLhixTAJU9BgmSsTRhI9luI5B_pt7wEQ96wCWnG6ndWCKSkHddCBFQpXOdfRtw3y-7J67R99ujUCTMCc71pLBrBx3ebUvDaThfcvPLXwvlJr6Aq2lIY6BJ3y1Vs1auRIRnaT1veOxDUZIDtW5nWeiKspYqYFiDHvM3oP9eE7gUGkOzh_mQGjNre4RSU_IRlcV2NP8R_ofaxBMg-7rHOt22TWH29wy5frV1ZgPu5uA-YynAMu2B-hB6M6ZqU9eJIqdtbvHWgdftgc5IGHlXG0RYILQJ-NRxmI8iWmX4LaE05F0qlHHy4JTzhEjLdfAL9x_rL8ju9J_h2XKZGHZs_pJu26adOwZoetIZkeLg3e-KD-CyvbPeTzdaZ3CP5UK6hfzdLhxqvEBCCSdaeK4CMSJ0NK1vtD_QO88x0jcc449F9YdA82Ebnu-_AZNnGOQk2NxYdp-rvAe8u1RQsfuS7yFQ';

	/**
	 * Cache duration for API data (20 minutes as requested).
	 *
	 * @var int
	 */
	private $cache_duration = 20 * MINUTE_IN_SECONDS;
	
	/**
	 * Constructor.
	 */
	public function __construct() {
		// Add AJAX handlers
		add_action( 'wp_ajax_fetch_calendar_data', array( $this, 'fetch_calendar_data_callback' ) );
		add_action( 'wp_ajax_nopriv_fetch_calendar_data', array( $this, 'fetch_calendar_data_callback' ) );
	}
	
	/**
	 * Kate & Tom's stay duration definitions.
	 *
	 * @var array
	 */
	private $stay_durations = array(
		'2-night-weekend' => array(
			'start_day' => 5, // Friday (1=Monday, 5=Friday)
			'nights' => 2,
			'checkout_day' => 0 // Sunday
		),
		'3-night-weekend' => array(
			'start_day' => 5, // Friday 
			'nights' => 3,
			'checkout_day' => 1 // Monday
		),
		'week' => array(
			'start_day' => array(5, 1), // Friday OR Monday
			'nights' => 7,
			'checkout_day' => array(5, 1) // Friday OR Monday
		),
		'midweek' => array(
			'start_day' => 1, // Monday
			'nights' => 4,
			'checkout_day' => 5 // Friday
		),
		'2-night-midweek' => array(
			'start_day' => array(1, 2, 3), // Monday, Tuesday, or Wednesday
			'nights' => 2,
			'checkout_day' => 'variable' // +1 day from checkin
		)
	);
	
	/**
	 * Get calendar data for a specific house.
	 *
	 * @param string $house_id House ID for API calls.
	 * @param string $access_token API access token.
	 * @param bool   $force_refresh Force refresh of cached data.
	 * @return array Processed calendar data.
	 */
	public function get_calendar_data( $house_id, $access_token, $force_refresh = false ) {
		$transient_key = "kt_house_calendar_{$house_id}";
		
		if ( ! $force_refresh ) {
			$cached_data = get_transient( $transient_key );
			if ( false !== $cached_data ) {
				return $cached_data;
			}
		}
		
		// Fetch availability and rates data
		$availability_data = $this->fetch_availability_data( $house_id, $access_token );
		$rates_data = $this->fetch_rates_data( $house_id, $access_token );
		
		// Debug: Log raw API responses
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'Raw availability data: ' . print_r( $availability_data, true ) );
			error_log( 'Raw rates data: ' . print_r( $rates_data, true ) );
		}
		
		if ( is_wp_error( $availability_data ) || is_wp_error( $rates_data ) ) {
			$error_msg = 'Failed to fetch calendar data';
			if ( is_wp_error( $availability_data ) ) {
				$error_msg .= ' - Availability: ' . $availability_data->get_error_message();
			}
			if ( is_wp_error( $rates_data ) ) {
				$error_msg .= ' - Rates: ' . $rates_data->get_error_message();
			}
			return array( 'error' => $error_msg );
		}
		
		// Process data with Kate & Tom's logic
		$calendar_data = $this->process_kt_calendar_data( $availability_data, $rates_data );
		
		// TEMPORARY DEBUG: Return raw data to see what we're getting
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			$calendar_data['debug_raw_availability'] = $availability_data;
			$calendar_data['debug_raw_rates'] = $rates_data;
		}
		
		// Cache the processed data
		set_transient( $transient_key, $calendar_data, $this->cache_duration );
		
		return $calendar_data;
	}
	
	/**
	 * Process Kate & Tom's calendar data with business logic.
	 *
	 * @param array $availability Raw availability data from API.
	 * @param array $rates Raw rates data from API.
	 * @return array Processed calendar data.
	 */
	private function process_kt_calendar_data( $availability, $rates ) {
		$processed_data = array(
			'availability' => array(),
			'rates' => array(),
			'periods' => array(),
			'processed_at' => current_time( 'timestamp' ),
			'stay_types' => $this->stay_durations
		);
		
		// Debug: Log what we're processing
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'Processing availability structure: ' . print_r( array_keys( $availability ), true ) );
			error_log( 'Processing rates structure: ' . print_r( array_keys( $rates ), true ) );
		}
		
		// Process availability data for each day
		if ( is_array( $availability ) ) {
			foreach ( $availability as $year => $months ) {
				if ( is_array( $months ) ) {
					foreach ( $months as $month => $days ) {
						if ( is_array( $days ) ) {
							foreach ( $days as $day => $status ) {
								$date = sprintf( '%04d-%02d-%02d', $year, $month, $day );
								$processed_data['availability'][$date] = $this->process_day_availability( $date, $status );
							}
						}
					}
				}
			}
		}
		
		// Process rates with Kate & Tom's pricing keys
		if ( isset( $rates['Rates'] ) && is_array( $rates['Rates'] ) ) {
			foreach ( $rates['Rates'] as $rate_period ) {
				$month_key = $rate_period['Month'];
				$processed_data['rates'][$month_key] = $this->process_kt_rates( $rate_period );
			}
		}
		
		// Debug: Log final processed counts
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'Processed availability days: ' . count( $processed_data['availability'] ) );
			error_log( 'Processed rate periods: ' . count( $processed_data['rates'] ) );
		}
		
		return $processed_data;
	}
	
	/**
	 * Process individual day availability.
	 *
	 * @param string $date Date in Y-m-d format.
	 * @param string $api_status Status from API (A/B/OB).
	 * @return array Processed day data.
	 */
	private function process_day_availability( $date, $api_status ) {
		$day_of_week = gmdate( 'N', strtotime( $date ) ); // 1=Monday, 7=Sunday
		
		// Convert API status to internal status
		$status_map = array(
			'A' => 'available',
			'B' => 'booked',
			'OB' => 'owner_blocked',
		);
		$status = $status_map[$api_status] ?? 'unknown';
		
		$processed_day = array(
			'status' => $status,
			'api_status' => $api_status,
			'day_of_week' => $day_of_week,
			'is_checkin' => false,
			'is_checkout' => false,
			'diagonal_style' => 'none'
		);
		
		// Determine if this day is a check-in or checkout based on Kate & Tom's logic
		$processed_day = $this->determine_checkin_checkout( $date, $processed_day, $api_status );
		
		return $processed_day;
	}
	
	/**
	 * Determine check-in/checkout status for a day.
	 *
	 * @param string $date Date in Y-m-d format.
	 * @param array  $processed_day Current day data.
	 * @param string $api_status Original API status.
	 * @return array Updated day data.
	 */
	private function determine_checkin_checkout( $date, $processed_day, $api_status ) {
		$day_of_week = $processed_day['day_of_week'];
		$status = $processed_day['status'];
		
		// Only process if day is available
		if ( 'booked' === $status || 'owner_blocked' === $status ) {
			return $processed_day;
		}
		
		// Check for check-in days (Friday for weekends, Monday for midweek/week)
		if ( in_array( $day_of_week, array( 1, 5 ), true ) && 'available' === $status ) {
			$processed_day['is_checkin'] = true;
			$processed_day['diagonal_style'] = 'halfafter'; // Matches existing CSS class
		}
		
		// Check for checkout days (Sunday, Monday, Friday based on stay type)
		if ( in_array( $day_of_week, array( 0, 1, 5 ), true ) && 'available' === $status ) {
			// Additional logic to determine if this is actually a checkout day
			$next_day_status = $this->get_next_day_status( $date );
			if ( 'booked' === $next_day_status || 'unavailable' === $next_day_status ) {
				$processed_day['is_checkout'] = true;
				$processed_day['diagonal_style'] = 'halfbefore'; // Matches existing CSS class
			}
		}
		
		return $processed_day;
	}
	
	/**
	 * Process Kate & Tom's rates data.
	 *
	 * @param array $rate_period Rate data for a specific month.
	 * @return array Processed rates data.
	 */
	private function process_kt_rates( $rate_period ) {
		$processed_rates = array(
			'month' => $rate_period['Month'],
			'notes' => $rate_period['Notes'] ?? '',
			'weeks' => array(),
		);
		
		foreach ( $rate_period['WeekPriceList'] as $week_data ) {
			$week_commencing = $week_data['WeekCommencing'];
			$processed_rates['weeks'][$week_commencing] = array();
			
			foreach ( $week_data['Amount'] as $stay_code => $rate ) {
				$processed_rate = $this->process_single_rate( $rate );
				$processed_rates['weeks'][$week_commencing][$stay_code] = $processed_rate;
			}
		}
		
		return $processed_rates;
	}
	
	/**
	 * Process individual rate with Kate & Tom's pricing keys.
	 *
	 * @param mixed $rate Rate value (string or numeric).
	 * @return array Processed rate data.
	 */
	private function process_single_rate( $rate ) {
		// Handle Kate & Tom's special pricing keys
		if ( is_string( $rate ) ) {
			// Check for rates with special offers (contains * but is numeric)
			if ( preg_match( '/^(\d+)\s*(\*+)?$/', trim( $rate ), $matches ) ) {
				$numeric_value = intval( $matches[1] );
				$offer_stars = $matches[2] ?? '';
				return array(
					'type' => 'price',
					'display' => '£' . number_format( $numeric_value ),
					'value' => $numeric_value,
					'offer' => strlen( $offer_stars ),
				);
			}

			switch ( trim( $rate ) ) {
				case '+':
					return array( 'type' => 'from', 'display' => 'Prices From', 'value' => null );
				case '-2':
					return array( 'type' => 'hidden', 'display' => '', 'value' => null );
				case '*':
					return array( 'type' => 'special1', 'display' => 'Special Offer*', 'value' => null );
				case '**':
					return array( 'type' => 'special2', 'display' => 'Special Offer**', 'value' => null );
				case '***':
					return array( 'type' => 'special3', 'display' => 'Special Offer***', 'value' => null );
				case '-1':
					return array( 'type' => 'no', 'display' => 'n/a', 'value' => null );
				case '0':
					return array( 'type' => 'previous', 'display' => 'See Previous Week', 'value' => 0 );
				default:
					// Try to parse as numeric
					if ( is_numeric( $rate ) ) {
						$numeric_value = floatval( $rate );
						return array(
							'type' => 'price',
							'display' => '£' . number_format( $numeric_value ),
							'value' => $numeric_value,
						);
					}
					return array(
						'type' => 'unknown',
						'display' => esc_html( $rate ),
						'value' => null,
					);
			}
		}
		
		// Numeric rate
		if ( is_numeric( $rate ) && $rate > 0 ) {
			return array( 'type' => 'price', 'display' => '£' . number_format( $rate ), 'value' => $rate );
		}
		
		return array( 'type' => 'unavailable', 'display' => 'n/a', 'value' => null );
	}
	
	/**
	 * Get next day status for checkout determination.
	 *
	 * @param string $date Current date.
	 * @return string Next day status.
	 */
	private function get_next_day_status( $date ) {
		$next_day = gmdate( 'Y-m-d', strtotime( $date . ' +1 day' ) );
		// This would need to check the availability data
		// Implementation depends on your data structure
		return 'unknown';
	}
	
	/**
	 * Fetch availability data from API.
	 *
	 * @param string $house_id House ID.
	 * @param string $access_token API access token.
	 * @return array|WP_Error Availability data or error.
	 */
	private function fetch_availability_data( $house_id, $access_token ) {
		$url = $this->api_base_url . "/{$house_id}/dayavailability?access_token={$access_token}";
		$response = wp_remote_get(
			$url,
			array(
				'timeout' => 30,
				'headers' => array(
					'User-Agent' => 'Kate-Toms-Calendar/1.0',
				),
			)
		);
		
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );
		
		if ( JSON_ERROR_NONE !== json_last_error() ) {
			return new WP_Error( 'json_decode_error', 'Failed to decode JSON response' );
		}
		
		return $data;
	}
	
	/**
	 * Fetch rates data from API.
	 *
	 * @param string $house_id House ID.
	 * @param string $access_token API access token.
	 * @return array|WP_Error Rates data or error.
	 */
	private function fetch_rates_data( $house_id, $access_token ) {
		$url = $this->api_base_url . "/{$house_id}/customrates?access_token={$access_token}";
		$response = wp_remote_get(
			$url,
			array(
				'timeout' => 30,
				'headers' => array(
					'User-Agent' => 'Kate-Toms-Calendar/1.0',
				),
			)
		);
		
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );
		
		if ( JSON_ERROR_NONE !== json_last_error() ) {
			return new WP_Error( 'json_decode_error', 'Failed to decode JSON response' );
		}
		
		return $data;
	}
	
	/**
	 * AJAX callback for fetching calendar data.
	 */
	public function fetch_calendar_data_callback() {
		// Verify nonce
		if ( ! wp_verify_nonce( wp_unslash( $_POST['nonce'] ?? '' ), 'calendar_data_nonce' ) ) {
			wp_die( 'Security check failed' );
		}

		$house_id = sanitize_text_field( wp_unslash( $_POST['house_id'] ?? '' ) );
		
		if ( empty( $house_id ) ) {
			wp_send_json_error( 'Missing house ID parameter' );
		}

		// Use the hardcoded access token
		$data = $this->get_calendar_data( $house_id, $this->api_access_token );
		
		if ( isset( $data['error'] ) ) {
			wp_send_json_error( $data['error'] );
		}
		
		wp_send_json_success( $data );
	}
}
