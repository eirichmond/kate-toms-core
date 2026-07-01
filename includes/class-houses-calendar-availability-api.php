<?php

/**
 * House Calendar Manager with Kate & Tom's specific business logic.
 *
 * Handles availability and rates data from the booking API with
 * Kate & Tom's specific pricing keys and stay duration logic.
 * 
 * Note on KT's period rates and checkin/checkout days:
 * 2 night weekend = Friday(1) +1day checkout Sunday
 * 3 night weekend = Friday(1) +2day checkout Monday
 * Week = Friday(1) +6day checkout Friday
 * Midweek = Monday(1) +3day checkout Friday
 * 2 night midweek = Monday, Tues or Weds(1) +1day checkout +1day from checkin
 * 
 * 
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
	 * OAuth token endpoint URL.
	 *
	 * @var string
	 */
	private $oauth_token_url = 'https://booking.kateandtoms.com/oauth/2.0/token';

	/**
	 * OAuth authorization header (Base64 encoded client credentials).
	 *
	 * @var string
	 */
	private $oauth_auth_header = 'Basic MTAwMTo2NDNmNjc5NTA1NTA0ZDE3OTY1NDQ2NDdkMTFjNWIwMA==';

	/**
	 * API access token for booking system.
	 *
	 * @var string
	 */
	private $api_access_token = '';

	/**
	 * Transient key for storing the access token.
	 *
	 * @var string
	 */
	private $token_transient_key = 'kt_booking_api_access_token';

	/**
	 * Cache duration for API data.
	 * Set to 24 hours. The full warm must be run via WP CLI (too slow for web cron).
	 * Incremental updates (every 5 min via web cron) refresh properties with rate changes.
	 *
	 * @var int
	 */
	private $cache_duration = 24 * HOUR_IN_SECONDS;

	/**
	 * Temporarily stores processed availability data during processing.
	 * Used by helper functions to check adjacent day statuses.
	 *
	 * @var array
	 */
	private $processed_availability = array();

	/**
	 * Constructor.
	 */
	public function __construct() {
		// Initialize access token on construction
		$this->api_access_token = $this->get_access_token();

		// Add AJAX handlers
		add_action( 'wp_ajax_fetch_calendar_data', array( $this, 'fetch_calendar_data_callback' ) );
		add_action( 'wp_ajax_nopriv_fetch_calendar_data', array( $this, 'fetch_calendar_data_callback' ) );

		// Lightweight AJAX handler for just availability notes
		add_action( 'wp_ajax_get_availability_notes', array( $this, 'get_availability_notes_callback' ) );
		add_action( 'wp_ajax_nopriv_get_availability_notes', array( $this, 'get_availability_notes_callback' ) );

		// AJAX handler for booking date selection
		add_action( 'wp_ajax_get_house_booking_data', array( $this, 'get_house_booking_data_callback' ) );
		add_action( 'wp_ajax_nopriv_get_house_booking_data', array( $this, 'get_house_booking_data_callback' ) );

		// AJAX handler for booking periods
		add_action( 'wp_ajax_get_booking_periods', array( $this, 'get_booking_periods_callback' ) );
		add_action( 'wp_ajax_nopriv_get_booking_periods', array( $this, 'get_booking_periods_callback' ) );

		// AJAX handler for booking enquiry submission
		add_action( 'wp_ajax_submit_booking_enquiry', array( $this, 'submit_booking_enquiry_callback' ) );
		add_action( 'wp_ajax_nopriv_submit_booking_enquiry', array( $this, 'submit_booking_enquiry_callback' ) );
	}

	/**
	 * Get access token, refreshing if necessary.
	 *
	 * Checks transient cache first, then refreshes if expired or missing.
	 *
	 * @return string Access token.
	 */
	private function get_access_token() {
		// Try to get cached token
		$cached_token = get_transient( $this->token_transient_key );

		if ( false !== $cached_token && ! empty( $cached_token ) ) {
			$this->api_access_token = $cached_token;
			return $cached_token;
		}

		// Token expired or missing, refresh it
		return $this->refresh_access_token();
	}

	/**
	 * Refresh the OAuth access token.
	 *
	 * Makes a request to the OAuth token endpoint and stores the token.
	 * Uses cURL as requested, but could be converted to wp_remote_post() for WordPress best practices.
	 *
	 * @return string|false Access token on success, false on failure.
	 */
	private function refresh_access_token() {
		// Use cURL as provided in user's request
		$curl = curl_init();

		curl_setopt_array(
			$curl,
			array(
				CURLOPT_URL            => $this->oauth_token_url,
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_ENCODING       => '',
				CURLOPT_MAXREDIRS      => 10,
				CURLOPT_TIMEOUT        => 30,
				CURLOPT_FOLLOWLOCATION => true,
				CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
				CURLOPT_CUSTOMREQUEST  => 'POST',
				CURLOPT_POSTFIELDS     => 'grant_type=client_credentials',
				CURLOPT_HTTPHEADER     => array(
					'Authorization: ' . $this->oauth_auth_header,
					'Content-Type: application/x-www-form-urlencoded',
				),
			)
		);

		$response   = curl_exec( $curl );
		$http_code  = curl_getinfo( $curl, CURLINFO_HTTP_CODE );
		$curl_error = curl_error( $curl );

		curl_close( $curl );

		// Check for cURL errors.
		if ( false === $response || ! empty( $curl_error ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'OAuth token refresh cURL error: ' . $curl_error );
			}
			return false;
		}

		// Check HTTP status code.
		if ( 200 !== $http_code ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'OAuth token refresh failed with HTTP code: ' . $http_code );
				error_log( 'Response: ' . $response );
			}
			return false;
		}

		// Parse JSON response.
		$data = json_decode( $response, true );

		if ( JSON_ERROR_NONE !== json_last_error() || ! isset( $data['access_token'] ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'OAuth token refresh failed to parse response: ' . json_last_error_msg() );
				error_log( 'Response: ' . $response );
			}
			return false;
		}

		$token = $data['access_token'];

		// Calculate expiry time (default to 1 hour if not provided, with 5 minute buffer).
		$expires_in   = isset( $data['expires_in'] ) ? (int) $data['expires_in'] : 3600;
		$expiry_time  = $expires_in - 300; // Subtract 5 minutes as buffer.

		// Store token in transient (convert to seconds, ensure minimum 1 minute).
		$transient_expiry = max( MINUTE_IN_SECONDS, $expiry_time );
		set_transient( $this->token_transient_key, $token, $transient_expiry );

		// Update class property.
		$this->api_access_token = $token;

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'OAuth token refreshed successfully. Expires in: ' . $expires_in . ' seconds' );
		}

		return $token;
	}

	/**
	 * Kate & Tom's stay duration definitions.
	 *
	 * @var array
	 */
	private $stay_durations = array(
		'2-night-weekend' => array(
			'start_day'    => 5, // Friday (1=Monday, 5=Friday)
			'nights'       => 2,
			'checkout_day' => 0, // Sunday
		),
		'3-night-weekend' => array(
			'start_day'    => 5, // Friday
			'nights'       => 3,
			'checkout_day' => 1, // Monday
		),
		'week'            => array(
			'start_day'    => array( 5, 1 ), // Friday OR Monday
			'nights'       => 7,
			'checkout_day' => array( 5, 1 ), // Friday OR Monday
		),
		'midweek'         => array(
			'start_day'    => 1, // Monday
			'nights'       => 4,
			'checkout_day' => 5, // Friday
		),
		'2-night-midweek' => array(
			'start_day'    => array( 1, 2, 3 ), // Monday, Tuesday, or Wednesday
			'nights'       => 2,
			'checkout_day' => 'variable', // +1 day from checkin
		),
	);

	/**
	 * Get calendar data for a specific house.
	 *
	 * @param string $house_id House ID for API calls.
	 * @param string $access_token API access token.
	 * @param bool   $force_refresh Force refresh of cached data.
	 * @return array Processed calendar data.
	 */
	public function get_calendar_data( $house_id, $access_token, $force_refresh = true ) {
		$transient_key = "kt_house_calendar_{$house_id}";

		if ( ! $force_refresh ) {
			$cached_data = get_transient( $transient_key );
			if ( false !== $cached_data ) {
				return $cached_data;
			}
		}

		// Fetch availability and rates data
		$availability_data = $this->fetch_availability_data( $house_id, $access_token );
		$rates_data        = $this->fetch_rates_data( $house_id, $access_token );

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
			$calendar_data['debug_raw_rates']        = $rates_data;
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
		// Initialize temporary storage for raw availability statuses
		// Build a flat array of all raw statuses BEFORE processing
		// This allows us to check adjacent day statuses during processing.
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'Processing rates structure: ' . print_r( array_keys( $rates ), true ) );
		}

		// Cap availability to the last month that has rate data.
		// The API returns availability far beyond the rates range, which causes
		// unnecessary calendars to be rendered.
		$availability = $this->cap_availability_to_rates( $availability, $rates );

		$this->processed_availability = array();

		$processed_data = array(
			'availability' => array(),
			'rates'        => array(),
			'periods'      => array(),
			'processed_at' => current_time( 'timestamp' ),
			'stay_types'   => $this->stay_durations,
		);

		// Debug: Log what we're processing
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'Processing availability structure: ' . print_r( array_keys( $availability ), true ) );
			error_log( 'Processing rates structure: ' . print_r( array_keys( $rates ), true ) );
		}

		// FIRST PASS: Build flat array of raw statuses for all days
		// This allows us to check adjacent days during processing
		if ( is_array( $availability ) ) {
			foreach ( $availability as $year => $months ) {
				if ( is_array( $months ) ) {
					foreach ( $months as $month => $days ) {
						if ( is_array( $days ) ) {
							// Clean up raw API data before storing
							$days = $this->clean_raw_availability_data( $days );

							foreach ( $days as $day => $status ) {
								$date = sprintf( '%04d-%02d-%02d', $year, $month, $day );
								// Store raw status with normalized values
								$status_map                            = array(
									'A'  => 'available',
									'B'  => 'booked',
									'P'  => 'booked',
									'OB' => 'owner_blocked',
									'U'  => 'booked',
								);
								$this->processed_availability[ $date ] = array(
									'status' => $status_map[ $status ] ?? 'unknown',
								);
							}
						}
					}
				}
			}
		}

		// SECOND PASS: Process each day with full logic now that we have all raw statuses
		if ( is_array( $availability ) ) {
			foreach ( $availability as $year => $months ) {
				if ( is_array( $months ) ) {
					foreach ( $months as $month => $days ) {
						if ( is_array( $days ) ) {
							// Clean up raw API data before processing
							$days = $this->clean_raw_availability_data( $days );

							foreach ( $days as $day => $status ) {
								$date          = sprintf( '%04d-%02d-%02d', $year, $month, $day );
								$processed_day = $this->process_day_availability( $date, $status );

								// Store in the return array
								$processed_data['availability'][ $date ] = $processed_day;
							}
						}
					}
				}
			}
		}

		// THIRD PASS: Track bookable periods and apply checkout day classes
		// This handles cases where a booked Mon/Fri is the end of an available period
		$this->apply_bookable_period_logic( $processed_data['availability'] );

		// Preserve AvailabilityNotes from raw rates data
		if ( isset( $rates['AvailabilityNotes'] ) ) {
			$processed_data['AvailabilityNotes'] = $rates['AvailabilityNotes'];
		}

		// Process rates with Kate & Tom's pricing keys
		if ( isset( $rates['Rates'] ) && is_array( $rates['Rates'] ) ) {
			foreach ( $rates['Rates'] as $rate_period ) {
				$month_key             = $rate_period['Month'];
				$processed_month_rates = $this->process_kt_rates( $rate_period, $processed_data['availability'] );

				// Only store if not null (i.e., not a past month)
				if ( null !== $processed_month_rates ) {
					$processed_data['rates'][ $month_key ] = $processed_month_rates;
				}
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
	 * Cap availability data to only include months covered by rates.
	 *
	 * The availability API can return data far beyond the rates range (e.g.
	 * availability through 2028 but rates only to Dec 2026). This trims the
	 * availability array so we don't process and render unnecessary calendars.
	 *
	 * @param array $availability Raw availability data keyed by year > month > day.
	 * @param array $rates Raw rates data from API.
	 * @return array Filtered availability data.
	 */
	private function cap_availability_to_rates( $availability, $rates ) {
		if ( ! isset( $rates['Rates'] ) || ! is_array( $rates['Rates'] ) || empty( $rates['Rates'] ) ) {
			return $availability;
		}

		// Find the last month in the rates data.
		$last_rate_month = null;
		foreach ( $rates['Rates'] as $rate_period ) {
			if ( isset( $rate_period['Month'] ) && $rate_period['Month'] > $last_rate_month ) {
				$last_rate_month = $rate_period['Month'];
			}
		}

		if ( ! $last_rate_month ) {
			return $availability;
		}

		// Parse the last rate month (format: "2026-12").
		$parts      = explode( '-', $last_rate_month );
		$cap_year   = (int) $parts[0];
		$cap_month  = (int) $parts[1];

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( "Capping availability to last rate month: {$last_rate_month}" );
		}

		// Filter availability to only include data up to the cap month.
		$filtered = array();
		foreach ( $availability as $year => $months ) {
			$year_int = (int) $year;
			if ( $year_int > $cap_year ) {
				continue;
			}
			if ( ! is_array( $months ) ) {
				continue;
			}
			foreach ( $months as $month => $days ) {
				$month_int = (int) $month;
				if ( $year_int === $cap_year && $month_int > $cap_month ) {
					continue;
				}
				$filtered[ $year ][ $month ] = $days;
			}
		}

		return $filtered;
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
			'A'  => 'available',
			'B'  => 'booked',
			'P'  => 'booked',
			'OB' => 'owner_blocked',
			'U'  => 'booked',
		);
		$status     = $status_map[ $api_status ] ?? 'unknown';

		$processed_day = array(
			'status'         => $status,
			'api_status'     => $api_status === 'P' ? 'B' : $api_status,
			'day_of_week'    => $day_of_week,
			'is_checkin'     => false,
			'is_checkout'    => false,
			'diagonal_style' => 'none',
			'bk_avail'       => false,
			'bookable_from'  => null,
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
		$status = $processed_day['status'];

		// Get adjacent day statuses.
		$prev_day_status = $this->get_previous_day_status( $date );
		$next_day_status = $this->get_next_day_status( $date );

		$is_prev_booked = in_array( $prev_day_status, array( 'booked', 'owner_blocked' ), true );
		$is_next_booked = in_array( $next_day_status, array( 'booked', 'owner_blocked' ), true );

		// Diagonals only appear on the FIRST day of a new status:
		// - First BOOKED day (prev=available): halfbefore (green-to-red) = check-in.
		// - First AVAILABLE day after booking (prev=booked): halfafter (red-to-green) = check-out.

		// AVAILABLE days.
		if ( 'available' === $status ) {
			// First available after booking = checkout (guests departed this morning).
			if ( $is_prev_booked ) {
				$processed_day['is_checkout']    = true;
				$processed_day['diagonal_style'] = 'halfafter';

				// Changeover: also last available before next booking.
				if ( $is_next_booked ) {
					$processed_day['is_checkin']     = true;
					$processed_day['diagonal_style'] = 'halfafter halfbefore';
				}
			}
		}

		// BOOKED days.
		if ( 'booked' === $status ) {
			// First booked day after available period = checkin (guests arriving).
			if ( 'available' === $prev_day_status ) {
				$processed_day['is_checkin']     = true;
				$processed_day['diagonal_style'] = 'halfbefore';
				$processed_day['bk_avail']       = true;
			}
		}

		return $processed_day;
	}

	/**
	 * Process Kate & Tom's rates data.
	 *
	 * note:
	 *  2 night weekend = Friday(checkin day) +1day checkout Sunday
	 *  3 night weekend = Friday(checkin day) +2day checkout Monday
	 *  Week = Friday(checkin day) +6day checkout Friday || Monday(also checkin day) +6day checkout Monday
	 *  Midweek = Monday(checkin day) +3day checkout Friday
	 *  2 night midweek = Monday, Tues or Weds(checkin days) +1day checkout +1day from checkin
	 *
	 * @param array $rate_period Rate data for a specific month.
	 * @param array $availability_data Processed availability data for validation.
	 * @return array Processed rates data.
	 */
	private function process_kt_rates( $rate_period, $availability_data = array() ) {
		// Skip processing if entire month is in the past
		$month_key               = $rate_period['Month'];
		$month_timestamp         = strtotime( $month_key . '-01' ); // Add day to make it a valid date
		$current_month_timestamp = strtotime( gmdate( 'Y-m-01' ) ); // First day of current month

		if ( $month_timestamp < $current_month_timestamp ) {
			return null; // Don't process past months
		}

		$processed_rates = array(
			'month' => $rate_period['Month'],
			'notes' => $rate_period['Notes'] ?? '',
			'weeks' => array(),
		);

		/**
		 * 2 night weekend = Friday(checkin day) +1day checkout Sunday
		 * 3 night weekend = Friday(checkin day) +2day checkout Monday
		 * Week = Friday(checkin day) +6day checkout Friday || Monday(also checkin day) +6day checkout Monday
		 * Midweek = Monday(checkin day) +3day checkout Friday
		 * 2 night midweek = Monday, Tues or Weds(checkin days) +1day checkout +1day from checkin
		 */

		foreach ( $rate_period['WeekPriceList'] as $week_data ) {
			$week_commencing = $week_data['WeekCommencing'];

			// check the dates left in the month from the $week_commencing date
			// check if the $availability_data[date][status] is available
			$dates_left_in_month = $this->get_dates_left_in_month( $week_commencing, $availability_data );

			if ( count( $dates_left_in_month ) < 5 ) {
				foreach ( $dates_left_in_month as $date ) {
					if ( $availability_data[ $date ]['status'] !== 'available' ) {
						break 2;
					}
				}
			}

			$processed_rates['weeks'][ $week_commencing ] = array();

			foreach ( $week_data['Amount'] as $stay_code => $rate ) {
				$processed_rate = $this->process_single_rate( $rate );

				// Only validate rates that have actual pricing data
				$should_validate = in_array( $processed_rate['type'], array( 'price', 'from', 'special1', 'special2', 'special3', 'previous' ), true );

				if ( $should_validate && ! empty( $availability_data ) ) {
					// Validate rate against availability data
					if ( $this->is_booking_period_available( $week_commencing, $stay_code, $availability_data ) ) {
						$processed_rates['weeks'][ $week_commencing ][ $stay_code ] = $processed_rate;

						// Debug logging
						if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
							error_log( "Rate validation PASSED: Week {$week_commencing}, Stay {$stay_code}, Type {$processed_rate['type']}" );
						}
					} else {
						// Override with unavailable if booking period isn't free
						$processed_rates['weeks'][ $week_commencing ][ $stay_code ] = array(
							'type'    => 'unavailable',
							'display' => 'Booked',
							'value'   => null,
						);

						// Debug logging
						if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
							error_log( "Rate validation FAILED: Week {$week_commencing}, Stay {$stay_code}, Original type {$processed_rate['type']} -> Marked as Booked" );
						}
					}
				} else {
					// Keep original processed rate (already unavailable, hidden, etc. or no availability data)
					$processed_rates['weeks'][ $week_commencing ][ $stay_code ] = $processed_rate;

					// Debug logging
					if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
						$reason = $should_validate ? 'no availability data' : 'rate already unavailable/hidden';
						error_log( "Rate validation SKIPPED: Week {$week_commencing}, Stay {$stay_code}, Reason: {$reason}" );
					}
				}
			}
		}

		return $processed_rates;
	}

	/**
	 * Get all dates remaining in the month from a given week commencing date.
	 *
	 * @param string $week_commencing Week commencing date in YYYY-MM-DD format.
	 * @param array  $availability_data Processed availability data indexed by date.
	 * @return array Array of date strings in YYYY-MM-DD format from week_commencing to end of month.
	 */
	private function get_dates_left_in_month( $week_commencing, $availability_data ) {
		$dates = array();

		try {
			$current_date = new DateTime( $week_commencing );
			$month        = (int) $current_date->format( 'm' );
			$year         = (int) $current_date->format( 'Y' );

			// Get the last day of this month.
			$last_day_of_month = new DateTime( $year . '-' . $month . '-01' );
			$last_day_of_month->modify( 'last day of this month' );

			// Loop through each day from week_commencing to end of month.
			while ( $current_date <= $last_day_of_month ) {
				$date_key = $current_date->format( 'Y-m-d' );

				// Only include dates that exist in availability data.
				if ( isset( $availability_data[ $date_key ] ) ) {
					$dates[] = $date_key;
				}

				$current_date->add( new DateInterval( 'P1D' ) );
			}
		} catch ( Exception $e ) {
			// Log error if date parsing fails.
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'Error getting dates left in month: ' . $e->getMessage() );
			}
		}

		return $dates;
	}

	/**
	 * Process individual rate with Kate & Tom's pricing keys.
	 *
	 * @param mixed $rate Rate value (string or numeric).
	 * @return array Processed rate data.
	 */
	private function process_single_rate( $rate ) {
		// Handle v2 API format where rate is an object with rental/mandatory_extras_total/total.
		$price_breakdown = null;
		if ( is_array( $rate ) && isset( $rate['total'] ) ) {
			$price_breakdown = array(
				'rental'                 => $rate['rental'] ?? null,
				'mandatory_extras_total' => $rate['mandatory_extras_total'] ?? null,
				'total'                  => $rate['total'],
			);
			// Use the total value for all downstream processing.
			$rate = $rate['total'];
		}

		$result = null;

		// Handle Kate & Tom's special pricing keys
		if ( is_string( $rate ) ) {
			// Check for rates with special offers (contains * but is numeric)
			if ( preg_match( '/^(\d+)\s*(\*+)?$/', trim( $rate ), $matches ) ) {
				$numeric_value = intval( $matches[1] );
				$offer_stars   = $matches[2] ?? '';
				$result        = array(
					'type'    => 'price',
					'display' => '£' . number_format( $numeric_value ),
					'value'   => $numeric_value,
					'offer'   => strlen( $offer_stars ),
				);
			}

			if ( null === $result ) {
				switch ( trim( $rate ) ) {
					case '+':
						$result = array(
							'type'    => 'from',
							'display' => 'Prices From',
							'value'   => null,
						);
						break;
					case '-2':
						$result = array(
							'type'    => 'hidden',
							'display' => '',
							'value'   => null,
						);
						break;
					case '*':
						$result = array(
							'type'    => 'special1',
							'display' => 'Special Offer*',
							'value'   => null,
						);
						break;
					case '**':
						$result = array(
							'type'    => 'special2',
							'display' => 'Special Offer**',
							'value'   => null,
						);
						break;
					case '***':
						$result = array(
							'type'    => 'special3',
							'display' => 'Special Offer***',
							'value'   => null,
						);
						break;
					case '-1':
						$result = array(
							'type'    => 'no',
							'display' => 'n/a',
							'value'   => null,
						);
						break;
					case '0':
						$result = array(
							'type'    => 'previous',
							'display' => 'See Previous Week',
							'value'   => 0,
						);
						break;
					default:
						// Try to parse as numeric
						if ( is_numeric( $rate ) ) {
							$numeric_value = floatval( $rate );
							$result        = array(
								'type'    => 'price',
								'display' => '£' . number_format( $numeric_value ),
								'value'   => $numeric_value,
							);
						} else {
							$result = array(
								'type'    => 'unknown',
								'display' => esc_html( $rate ),
								'value'   => null,
							);
						}
						break;
				}
			}
		}

		// Numeric rate
		if ( null === $result && is_numeric( $rate ) && $rate > 0 ) {
			$result = array(
				'type'    => 'price',
				'display' => '£' . number_format( $rate ),
				'value'   => $rate,
			);
		}

		if ( null === $result ) {
			$result = array(
				'type'    => 'unavailable',
				'display' => 'Booked',
				'value'   => null,
			);
		}

		// Attach v2 price breakdown if available.
		if ( $price_breakdown ) {
			$result['price_breakdown'] = $price_breakdown;
		}

		return $result;
	}

	/**
	 * Get next day status for checkout determination.
	 *
	 * @param string $date Current date.
	 * @return string Next day status (booked, available, unavailable, owner_blocked, or unknown).
	 */
	private function get_next_day_status( $date ) {
		$next_day = gmdate( 'Y-m-d', strtotime( $date . ' +1 day' ) );

		// Check if we have processed data for the next day
		if ( isset( $this->processed_availability[ $next_day ] ) ) {
			return $this->processed_availability[ $next_day ]['status'];
		}

		return 'unknown';
	}

	/**
	 * Get previous day status for checkin determination.
	 *
	 * @param string $date Current date.
	 * @return string Previous day status (booked, available, unavailable, owner_blocked, or unknown).
	 */
	private function get_previous_day_status( $date ) {
		$prev_day = gmdate( 'Y-m-d', strtotime( $date . ' -1 day' ) );

		// Check if we have processed data for the previous day
		if ( isset( $this->processed_availability[ $prev_day ] ) ) {
			return $this->processed_availability[ $prev_day ]['status'];
		}

		return 'unknown';
	}

	/**
	 * Apply bookable period logic to handle complex checkout scenarios.
	 *
	 * This tracks bookable periods and applies classes to booked days that represent
	 * the end of an available period (checkout days).
	 *
	 * @param array &$availability Reference to availability array to modify.
	 */
	private function apply_bookable_period_logic( &$availability ) {
		if ( empty( $availability ) || ! is_array( $availability ) ) {
			return;
		}

		// Sort dates chronologically.
		ksort( $availability );

		$bookable_period_start = null;

		foreach ( $availability as $date => &$day_data ) {
			$status = $day_data['status'];

			// Track start of bookable periods (any available day).
			if ( 'available' === $status ) {
				if ( null === $bookable_period_start ) {
					$bookable_period_start = $date;
				}
			}

			// Any booked/blocked day after an available period marks the checkout boundary.
			if ( 'booked' === $status || 'owner_blocked' === $status ) {
				if ( null !== $bookable_period_start ) {
					$day_data['bk_avail']      = true;
					$day_data['bookable_from'] = $bookable_period_start;

					// Reset the bookable period tracker.
					$bookable_period_start = null;
				}
			}
		}
	}

	/**
	 * Check if a booking period is available for a given stay type.
	 *
	 * @param string $week_commencing Week commencing date (YYYY-MM-DD format).
	 * @param string $stay_code Stay code (50, 60, 70, 80, 85, 90).
	 * @param array  $availability_data Processed availability data.
	 * @return bool True if entire booking period is available.
	 */
	private function is_booking_period_available( $week_commencing, $stay_code, $availability_data ) {
		$stay_details = $this->get_stay_details_for_code( $stay_code );

		if ( ! $stay_details ) {
			return false; // Unknown stay code
		}

		// Get possible checkin dates for this stay type
		$possible_checkin_dates = $this->get_checkin_dates_for_week( $week_commencing, $stay_details );

		// Check if any of the possible checkin scenarios work
		foreach ( $possible_checkin_dates as $checkin_date ) {
			if ( $this->validate_booking_period( $checkin_date, $stay_details['nights'], $availability_data ) ) {
				return true; // At least one scenario works
			}
		}

		return false; // No valid booking scenarios found
	}

	/**
	 * Get stay details for a rate code.
	 *
	 * @param string $stay_code Stay code (50, 60, 70, 80, 85, 90).
	 * @return array|false Stay details or false if unknown.
	 */
	private function get_stay_details_for_code( $stay_code ) {
		$stay_map = array(
			'50' => array(
				'nights'       => 2,
				'checkin_days' => array( 5 ),
			),         // 2-night weekend: Friday
			'60' => array(
				'nights'       => 3,
				'checkin_days' => array( 5 ),
			),         // 3-night weekend: Friday
			'70' => array(
				'nights'       => 7,
				'checkin_days' => array( 5, 1 ),
			),      // Week: Friday OR Monday
			'80' => array(
				'nights'       => 4,
				'checkin_days' => array( 1 ),
			),         // Midweek: Monday
			'85' => array(
				'nights'       => 2,
				'checkin_days' => array( 1, 2, 3 ),
			),   // 2-night midweek: Monday, Tuesday, Wednesday
			'90' => array(
				'nights'       => 5,
				'checkin_days' => array( 5 ),
			),         // 5 nights: Friday
		);

		return $stay_map[ $stay_code ] ?? false;
	}

	/**
	 * Get possible checkin dates for a week and stay type.
	 *
	 * @param string $week_commencing Week commencing date (Friday date).
	 * @param array  $stay_details Stay details including possible checkin days.
	 * @return array Array of possible checkin dates.
	 */
	private function get_checkin_dates_for_week( $week_commencing, $stay_details ) {
		$week_friday   = strtotime( $week_commencing );
		$checkin_dates = array();

		foreach ( $stay_details['checkin_days'] as $target_day ) {
			// Calculate offset from Friday (5) to target day
			// Friday = 5, Saturday = 6, Sunday = 0, Monday = 1, Tuesday = 2, Wednesday = 3, Thursday = 4
			$friday_day = 5;

			if ( $target_day >= $friday_day ) {
				// Same week: Saturday (6) = +1 day from Friday
				$offset = $target_day - $friday_day;
			} else {
				// Next week: Sunday (0) = +2, Monday (1) = +3, Tuesday (2) = +4, Wednesday (3) = +5
				$offset = ( 7 - $friday_day ) + $target_day;
			}

			$checkin_date    = gmdate( 'Y-m-d', $week_friday + ( $offset * DAY_IN_SECONDS ) );
			$checkin_dates[] = $checkin_date;
		}

		return $checkin_dates;
	}

	/**
	 * Validate that all days in a booking period are available.
	 *
	 * @param string $checkin_date Checkin date (YYYY-MM-DD format).
	 * @param int    $nights Number of nights to stay.
	 * @param array  $availability_data Processed availability data.
	 * @return bool True if all days are available.
	 */
	private function validate_booking_period( $checkin_date, $nights, $availability_data ) {
		$checkin_timestamp = strtotime( $checkin_date );

		// Check each day from checkin through the stay period
		for ( $i = 0; $i < $nights; $i++ ) {
			$check_date = gmdate( 'Y-m-d', $checkin_timestamp + ( $i * DAY_IN_SECONDS ) );

			// Get availability for this date
			$day_data = $availability_data[ $check_date ] ?? null;

			if ( ! $day_data || 'available' !== $day_data['status'] ) {
				// Day is not available - booking period fails validation
				return false;
			}
		}

		return true; // All days are available
	}

	/**
	 * Clean raw availability data from the API before processing.
	 *
	 * This function performs two cleanup operations:
	 * 1. Normalizes 'U' (unavailable) to 'B' (booked) for consistency
	 * 2. Converts single 'A' days sandwiched between unavailable days to 'B'
	 *    (since Kate & Tom's requires minimum 2-night stays)
	 *
	 * @param array $days Array of day => status for a month.
	 * @return array Cleaned days array.
	 */
	private function clean_raw_availability_data( $days ) {
		if ( ! is_array( $days ) ) {
			return $days;
		}

		// Step 1: Normalize 'U' (unavailable) to 'B' (booked)
		// Both mean the same thing in our business logic
		foreach ( $days as $day => $status ) {
			if ( 'U' === $status ) {
				$days[ $day ] = 'B';

				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( "Normalized unavailable status: Day {$day} changed from U to B" );
				}
			}
		}

		return $days;
	}

	/**
	 * Fix sandwiched available days - convert single "A" days between booked days to "B".
	 *
	 * @param array $days Array of day => status for a month.
	 * @return array Modified days array.
	 */
	private function fix_sandwiched_available_days( $days ) {
		if ( ! is_array( $days ) || count( $days ) < 3 ) {
			return $days;
		}

		$day_keys             = array_keys( $days );
		$unavailable_statuses = array( 'B', 'OB', 'U' );

		// Loop through days looking for sandwiched "A" values
		for ( $i = 1; $i < count( $day_keys ) - 1; $i++ ) {
			$prev_key    = $day_keys[ $i - 1 ];
			$current_key = $day_keys[ $i ];
			$next_key    = $day_keys[ $i + 1 ];

			// Check if current day is "A" and surrounded by unavailable days
			if ( 'A' === $days[ $current_key ] &&
				in_array( $days[ $prev_key ], $unavailable_statuses, true ) &&
				in_array( $days[ $next_key ], $unavailable_statuses, true )
			) {
				$days[ $current_key ] = 'B';

				// Debug log if enabled
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( "Fixed sandwiched available day: Day {$current_key} changed from A to B" );
				}
			}
		}

		return $days;
	}

	/**
	 * Fetch availability data from API.
	 *
	 * @param string $house_id House ID.
	 * @param string $access_token API access token.
	 * @return array|WP_Error Availability data or error.
	 */
	private function fetch_availability_data( $house_id, $access_token ) {
		$url      = $this->api_base_url . "/{$house_id}/dayavailability?access_token={$access_token}";
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
		$url      = $this->api_base_url . "/{$house_id}/customrates?access_token={$access_token}&version=2";
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
		// Verify nonce.
		if ( ! wp_verify_nonce( wp_unslash( $_POST['nonce'] ?? '' ), 'calendar_data_nonce' ) ) {
			wp_die( 'Security check failed' );
		}

		$house_id = sanitize_text_field( wp_unslash( $_POST['house_id'] ?? '' ) );

		if ( empty( $house_id ) ) {
			wp_send_json_error( 'Missing house ID parameter' );
		}

		// Use the hardcoded access token
		$data = $this->get_calendar_data( $house_id, $this->api_access_token, true );

		if ( isset( $data['error'] ) ) {
			wp_send_json_error( $data['error'] );
		}

		wp_send_json_success( $data );
	}

	/**
	 * Lightweight AJAX callback for fetching just availability notes.
	 */
	public function get_availability_notes_callback() {
		// Verify nonce
		if ( ! wp_verify_nonce( wp_unslash( $_GET['nonce'] ?? '' ), 'calendar_data_nonce' ) ) {
			wp_die( 'Security check failed' );
		}

		$house_id = sanitize_text_field( wp_unslash( $_GET['house_id'] ?? '' ) );

		if ( empty( $house_id ) ) {
			wp_send_json_error( 'Missing house ID parameter' );
		}

		// Get availability notes directly from transient cache
		$transient_key = '_transient_kt_house_calendar_' . $house_id;
		$calendar_data = get_option( $transient_key );

		$availability_notes = '';
		if ( $calendar_data && isset( $calendar_data['AvailabilityNotes'] ) ) {
			$availability_notes = $calendar_data['AvailabilityNotes'];
		}

		wp_send_json_success( array( 'AvailabilityNotes' => $availability_notes ) );
	}

	/**
	 * AJAX callback to get booking data for a specific house and date.
	 * Used when a user clicks on an available date in the calendar.
	 *
	 * @return void
	 */
	public function get_house_booking_data_callback() {
		// Verify nonce for security
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), 'calendar_booking_nonce' ) ) {
			wp_send_json_error( 'Invalid security token' );
		}

		$house_id = sanitize_text_field( wp_unslash( $_POST['house_id'] ?? '' ) );
		$date     = sanitize_text_field( wp_unslash( $_POST['date'] ?? '' ) );

		if ( empty( $house_id ) || empty( $date ) ) {
			wp_send_json_error( 'Missing required parameters' );
		}

		// Validate date format (YYYY-MM-DD)
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
			wp_send_json_error( 'Invalid date format' );
		}

		// Get the WordPress house post ID from the PropertyId (house_id)
		$wp_house_id = $this->get_wp_house_id_from_property_id( $house_id );

		if ( ! $wp_house_id ) {
			wp_send_json_error( 'House not found' );
		}

		// Calculate week number of the month for the given date
		$date_obj = DateTime::createFromFormat( 'Y-m-d', $date );
		if ( ! $date_obj ) {
			wp_send_json_error( 'Invalid date' );
		}

		$week_of_month = $this->calculate_week_of_month( $date_obj );

		// Get house details
		$house_post = get_post( $wp_house_id );
		if ( ! $house_post ) {
			wp_send_json_error( 'House post not found' );
		}

		// Format response data
		$response_data = array(
			'house_id'       => $wp_house_id,
			'property_id'    => $house_id,
			'house_name'     => $house_post->post_title,
			'house_slug'     => $house_post->post_name,
			'date'           => $date,
			'week'           => $week_of_month,
			'formatted_date' => $date_obj->format( 'l, j F Y' ),
			'booking_url'    => home_url( "/houses/{$house_post->post_name}/book/" ) . '?' . http_build_query(
				array(
					'd'    => $date_obj->format( 'd-m-Y' ),
					'week' => $week_of_month,
				)
			),
		);

		wp_send_json_success( $response_data );
	}

	/**
	 * Get WordPress house post ID from PropertyId using the property mapping.
	 *
	 * @param string $property_id The PropertyId from the API
	 * @return int|false WordPress post ID or false if not found
	 */
	private function get_wp_house_id_from_property_id( $property_id ) {
		// Get property mapping from transient or fetch from API
		$property_mapping = get_transient( 'kt_property_mapping' );

		if ( false === $property_mapping ) {
			$property_mapping = $this->fetch_property_mapping();

			if ( $property_mapping ) {
				// Cache for 1 hour
				set_transient( 'kt_property_mapping', $property_mapping, HOUR_IN_SECONDS );
			}
		}

		if ( ! $property_mapping ) {
			error_log( 'Failed to fetch property mapping for booking lookup' );
			return false;
		}

		// Find the WordPress house ID that corresponds to this PropertyId
		foreach ( $property_mapping as $property ) {
			if ( isset( $property['PropertyId'] ) && $property['PropertyId'] == $property_id ) {
				$wp_house_id = $property['PropertyReference'] ?? null;
				return $wp_house_id ? (int) $wp_house_id : false;
			}
		}

		error_log( "No WordPress house ID found for PropertyId: {$property_id}" );
		return false;
	}

	/**
	 * Fetch property mapping from the booking API.
	 *
	 * @return array|false Property mapping array or false on failure
	 */
	private function fetch_property_mapping() {
		$url = 'http://booking.kateandtoms.com/apis/properties/reflookup?access_token=' . urlencode( $this->api_access_token );

		$response = wp_remote_get(
			$url,
			array(
				'timeout' => 30,
				'headers' => array(
					'User-Agent' => 'Kate-Toms-Booking/1.0',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			error_log( 'Failed to fetch property mapping: ' . $response->get_error_message() );
			return false;
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		if ( $response_code !== 200 ) {
			error_log( "Property mapping API returned status code: {$response_code}" );
			return false;
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			error_log( 'Failed to decode property mapping JSON: ' . json_last_error_msg() );
			return false;
		}

		return $data;
	}

	/**
	 * Calculate the week number within a month for a given date.
	 *
	 * @param DateTime $date The date object
	 * @return int Week number (1-5)
	 */
	private function calculate_week_of_month( $date ) {
		$day = (int) $date->format( 'd' );
		return (int) ceil( $day / 7 );
	}

	/**
	 * Get available booking periods for a specific date and house.
	 *
	 * @param string   $property_id The iPro Property ID
	 * @param DateTime $checkin_date The desired check-in date
	 * @return array Available booking periods with pricing
	 */
	public function get_booking_periods_for_date( $property_id, $checkin_date ) {
		$periods = array();

		// Get calendar data using the correct transient key.
		$transient_key = "kt_house_calendar_{$property_id}";
		$calendar_data = get_transient( $transient_key );

		if ( false === $calendar_data || ! isset( $calendar_data['availability'] ) ) {
			// Try to fetch fresh data if cache miss.
			$calendar_data = $this->get_calendar_data( $property_id, $this->api_access_token );
			if ( ! $calendar_data || ! isset( $calendar_data['availability'] ) ) {
				return $periods;
			}
		}

		// Determine eligible periods based on the clicked arrival day.
		// Only periods starting ON this date are shown — no looking backwards.
		//
		// Arrival day rules:
		// Friday    → 2-night weekend, 3-night weekend, week (7 nights)
		// Saturday  → none
		// Sunday    → none
		// Monday    → 2-night midweek, midweek (4 nights), week (7 nights)
		// Tuesday   → 2-night midweek
		// Wednesday → 2-night midweek
		// Thursday  → none
		$arrival_day = (int) $checkin_date->format( 'N' ); // 1=Mon, 5=Fri, 7=Sun.

		$eligible_periods = array();

		switch ( $arrival_day ) {
			case 5: // Friday.
				$eligible_periods = array( '2-night-weekend', '3-night-weekend', 'week' );
				break;
			case 1: // Monday.
				$eligible_periods = array( '2-night-midweek', 'midweek', 'week' );
				break;
			case 2: // Tuesday.
			case 3: // Wednesday.
				$eligible_periods = array( '2-night-midweek' );
				break;
			default:
				// Saturday (6), Sunday (7), Thursday (4) — no breaks start on these days.
				break;
		}

		// Check each eligible period for availability and pricing.
		foreach ( $eligible_periods as $period_key ) {
			$period_config = $this->stay_durations[ $period_key ];
			$checkout_date = clone $checkin_date;
			$checkout_date->add( new DateInterval( 'P' . $period_config['nights'] . 'D' ) );

			// All nights in the period must be available.
			if ( ! $this->is_period_available_new( $checkin_date, $checkout_date, $calendar_data['availability'] ) ) {
				continue;
			}

			$period_price = $this->calculate_period_price_new( $period_key, $checkin_date, $calendar_data );

			if ( $period_price > 0 ) {
				$periods[] = array(
					'id'              => $period_key,
					'name'            => $this->get_period_display_name( $period_key ),
					'description'     => $this->get_period_description( $period_key, $checkin_date, $checkout_date ),
					'checkin_date'    => $checkin_date->format( 'Y-m-d' ),
					'checkout_date'   => $checkout_date->format( 'Y-m-d' ),
					'nights'          => $period_config['nights'],
					'price'           => $period_price,
					'formatted_price' => '£' . number_format( $period_price ),
				);
			}
		}

		// If no eligible periods exist for this arrival day (Sat, Sun, Thu),
		// return a message rather than searching for nearby dates.
		if ( empty( $eligible_periods ) ) {
			return array(
				'no_breaks' => true,
				'message'   => 'No breaks available from your chosen arrival day. Please get in touch to enquire or return and select another day',
			);
		}

		// If eligible periods exist but none passed availability/price checks,
		// search forward for the nearest valid checkin dates.
		if ( empty( $periods ) ) {
			$periods = $this->find_closest_booking_periods( $property_id, $checkin_date, $calendar_data );
		}

		return $periods;
	}

	/**
	 * Find closest available booking periods when the selected date is not a valid checkin day.
	 * Only searches forward from the selected date — never backwards.
	 *
	 * @param string   $property_id The iPro Property ID
	 * @param DateTime $selected_date The date the user clicked on
	 * @param array    $calendar_data Calendar data with availability and rates
	 * @return array Available booking periods from nearby future checkin dates
	 */
	private function find_closest_booking_periods( $property_id, $selected_date, $calendar_data ) {
		$all_periods   = array();
		$checked_dates = array();

		// Calculate the end of the current booking week (Thursday).
		// Booking weeks run Friday to Thursday.
		$selected_day_of_week = (int) $selected_date->format( 'N' ); // 1=Mon, 4=Thu, 5=Fri, 7=Sun.

		if ( $selected_day_of_week >= 5 ) {
			// Fri(5), Sat(6), Sun(7) — Thursday is 6, 5, or 4 days ahead.
			$days_to_thursday = 4 + ( 7 - $selected_day_of_week ); // Fri=6, Sat=5, Sun=4.
		} else {
			// Mon(1), Tue(2), Wed(3), Thu(4) — Thursday is 3, 2, 1, or 0 days ahead.
			$days_to_thursday = 4 - $selected_day_of_week;
		}

		// Only search forward within the same booking week (up to Thursday).
		for ( $days_offset = 1; $days_offset <= $days_to_thursday; $days_offset++ ) {
			$future_date = clone $selected_date;
			$future_date->add( new DateInterval( 'P' . $days_offset . 'D' ) );
			$future_periods = $this->get_periods_for_specific_date( $future_date, $calendar_data );

			foreach ( $future_periods as $period ) {
				$date_key = $period['checkin_date'];
				if ( ! isset( $checked_dates[ $date_key ] ) ) {
					$period['is_alternate']    = true;
					$period['days_difference'] = $days_offset;
					$all_periods[]             = $period;
					$checked_dates[ $date_key ] = true;
				}
			}

			// Once we've found periods and checked at least 3 days out, stop.
			if ( ! empty( $all_periods ) && $days_offset >= 3 ) {
				break;
			}
		}

		return $all_periods;
	}

	/**
	 * Get booking periods for a specific date without fallback searching.
	 *
	 * @param DateTime $checkin_date The checkin date to check
	 * @param array    $calendar_data Calendar data with availability and rates
	 * @return array Available booking periods for this specific date
	 */
	private function get_periods_for_specific_date( $checkin_date, $calendar_data ) {
		$periods             = array();
		$checkin_day_of_week = (int) $checkin_date->format( 'N' ); // 1=Monday, 7=Sunday

		// Check each stay duration to see if it's valid for this date
		foreach ( $this->stay_durations as $period_key => $period_config ) {
			$valid_start_days = is_array( $period_config['start_day'] )
				? $period_config['start_day']
				: array( $period_config['start_day'] );

			// Check if the checkin date falls on a valid start day for this period
			if ( in_array( $checkin_day_of_week, $valid_start_days ) ) {
				$checkout_date = clone $checkin_date;
				$checkout_date->add( new DateInterval( 'P' . $period_config['nights'] . 'D' ) );

				// Check availability for all nights in this period
				if ( $this->is_period_available_new( $checkin_date, $checkout_date, $calendar_data['availability'] ) ) {
					$period_price = $this->calculate_period_price_new( $period_key, $checkin_date, $calendar_data );

					if ( $period_price > 0 ) {
						$periods[] = array(
							'id'              => $period_key,
							'name'            => $this->get_period_display_name( $period_key ),
							'description'     => $this->get_period_description( $period_key, $checkin_date, $checkout_date ),
							'checkin_date'    => $checkin_date->format( 'Y-m-d' ),
							'checkout_date'   => $checkout_date->format( 'Y-m-d' ),
							'nights'          => $period_config['nights'],
							'price'           => $period_price,
							'formatted_price' => '£' . number_format( $period_price ),
						);
					}
				}
			}
		}

		return $periods;
	}

	/**
	 * Check if a period (range of dates) is available for booking.
	 *
	 * @param DateTime $checkin_date Start date
	 * @param DateTime $checkout_date End date
	 * @param array    $calendar_data Calendar data from API
	 * @return bool True if period is available
	 */
	private function is_period_available( $checkin_date, $checkout_date, $calendar_data ) {
		$current_date = clone $checkin_date;

		while ( $current_date < $checkout_date ) {
			$date_key = $current_date->format( 'Y-n-j' );

			// Check each month's data
			foreach ( $calendar_data['months'] as $month_data ) {
				if ( isset( $month_data['days'][ $date_key ] ) ) {
					$day_data = $month_data['days'][ $date_key ];

					// Check if day is available (not booked and not blocked)
					if ( isset( $day_data['status'] ) && $day_data['status'] !== 'available' ) {
						return false;
					}
				}
			}

			$current_date->add( new DateInterval( 'P1D' ) );
		}

		return true;
	}

	/**
	 * Check if a period (range of dates) is available for booking using new data structure.
	 *
	 * @param DateTime $checkin_date Start date
	 * @param DateTime $checkout_date End date
	 * @param array    $availability_data Availability data indexed by Y-m-d
	 * @return bool True if period is available
	 */
	private function is_period_available_new( $checkin_date, $checkout_date, $availability_data ) {
		$current_date = clone $checkin_date;

		while ( $current_date < $checkout_date ) {
			$date_key = $current_date->format( 'Y-m-d' );

			// Check if this date exists in availability data
			if ( ! isset( $availability_data[ $date_key ] ) ) {
				// No data for this date - consider unavailable
				return false;
			}

			$day_data = $availability_data[ $date_key ];

			// Check if day is available (not booked and not blocked)
			if ( isset( $day_data['status'] ) && $day_data['status'] !== 'available' ) {
				return false;
			}

			$current_date->add( new DateInterval( 'P1D' ) );
		}

		return true;
	}

	/**
	 * Calculate the total price for a booking period.
	 *
	 * @param DateTime $checkin_date Start date
	 * @param DateTime $checkout_date End date
	 * @param array    $calendar_data Calendar data with pricing
	 * @return int Total price in pounds
	 */
	private function calculate_period_price( $checkin_date, $checkout_date, $calendar_data ) {
		$total_price  = 0;
		$current_date = clone $checkin_date;

		while ( $current_date < $checkout_date ) {
			$date_key = $current_date->format( 'Y-n-j' );

			// Find price for this date in the calendar data
			foreach ( $calendar_data['months'] as $month_data ) {
				if ( isset( $month_data['days'][ $date_key ]['rates']['week'] ) ) {
					$total_price += (int) $month_data['days'][ $date_key ]['rates']['week'];
					break;
				}
			}

			$current_date->add( new DateInterval( 'P1D' ) );
		}

		return $total_price;
	}

	/**
	 * Calculate the total price for a booking period using new data structure.
	 *
	 * @param string   $period_key Stay type (2-night-weekend, 3-night-weekend, week, etc.)
	 * @param DateTime $checkin_date Start date
	 * @param array    $calendar_data Calendar data with rates structure
	 * @return int Total price in pounds
	 */
	private function calculate_period_price_new( $period_key, $checkin_date, $calendar_data ) {
		if ( ! isset( $calendar_data['rates'] ) ) {
			return 0;
		}

		// Map stay types to rate codes
		$stay_code_map = array(
			'2-night-weekend' => '50',
			'3-night-weekend' => '60',
			'week'            => '70',
			'midweek'         => '80',
			'2-night-midweek' => '85',
		);

		$stay_code = $stay_code_map[ $period_key ] ?? null;
		if ( ! $stay_code ) {
			return 0;
		}

		// Find the correct week commencing date
		// For K&T, weeks commence on Friday, so we need to find the Friday that starts the week containing our checkin date
		$checkin_day_of_week = (int) $checkin_date->format( 'N' ); // 1=Monday, 7=Sunday

		// Calculate days back to Friday (5)
		if ( $checkin_day_of_week >= 5 ) {
			// Same week Friday
			$days_back = $checkin_day_of_week - 5;
		} else {
			// Previous week Friday
			$days_back = $checkin_day_of_week + 2; // Mon=3, Tue=4, Wed=5, Thu=6
		}

		$week_commencing = clone $checkin_date;
		$week_commencing->sub( new DateInterval( 'P' . $days_back . 'D' ) );
		$week_commencing_key = $week_commencing->format( 'Y-m-d' );

		// Search through rates data for this week and stay code
		foreach ( $calendar_data['rates'] as $month_rates ) {
			if ( isset( $month_rates['weeks'][ $week_commencing_key ] ) ) {
				$week_rates = $month_rates['weeks'][ $week_commencing_key ];

				if ( isset( $week_rates[ $stay_code ] ) ) {
					$rate_data = $week_rates[ $stay_code ];

					// Return the price if it's available
					if ( isset( $rate_data['value'] ) && $rate_data['value'] > 0 && $rate_data['type'] === 'price' ) {
						return (int) $rate_data['value'];
					}
				}
			}
		}

		return 0;
	}

	/**
	 * Get display name for a booking period.
	 *
	 * @param string $period_key Period key
	 * @return string Display name
	 */
	private function get_period_display_name( $period_key ) {
		$names = array(
			'2-night-weekend' => '2 Night Weekend',
			'3-night-weekend' => '3 Night Weekend',
			'week'            => 'Week',
			'midweek'         => 'Midweek',
			'2-night-midweek' => '2 Night Midweek',
		);

		return isset( $names[ $period_key ] ) ? $names[ $period_key ] : ucwords( str_replace( '-', ' ', $period_key ) );
	}

	/**
	 * Get period description including days of week.
	 *
	 * @param string   $period_key Period key
	 * @param DateTime $checkin_date Check-in date
	 * @param DateTime $checkout_date Check-out date
	 * @return string Period description
	 */
	private function get_period_description( $period_key, $checkin_date, $checkout_date ) {
		$descriptions = array(
			'2-night-weekend' => 'Friday to Sunday',
			'3-night-weekend' => 'Friday to Monday',
			'week'            => '7 nights',
			'midweek'         => 'Monday to Friday',
		);

		if ( isset( $descriptions[ $period_key ] ) ) {
			return $descriptions[ $period_key ];
		}

		// Generate dynamic description using actual checkin/checkout days.
		// This covers 2-night-midweek which can start Mon, Tue or Wed.
		$checkin_day  = $checkin_date->format( 'l' );
		$checkout_day = $checkout_date->format( 'l' );

		return "{$checkin_day} to {$checkout_day}";
	}

	/**
	 * AJAX handler to get booking periods for a specific date.
	 */
	public function get_booking_periods_callback() {
		// Verify nonce for security
		if ( ! wp_verify_nonce( $_POST['nonce'], 'house_booking_nonce' ) ) {
			wp_die( 'Security check failed' );
		}

		$house_post_id = sanitize_text_field( $_POST['house_id'] ?? '' );
		$date_param    = sanitize_text_field( $_POST['date'] ?? '' );

		if ( ! $house_post_id || ! $date_param ) {
			wp_send_json_error( 'Missing required parameters' );
			return;
		}

		// Get the iPro PropertyId using the property mapping
		$property_id = $this->get_property_id_from_wp_house_id( (int) $house_post_id );
		if ( ! $property_id ) {
			wp_send_json_error( 'Unable to find property ID for this house' );
			return;
		}

		// Parse the date (dd-mm-yyyy format)
		$date_parts = explode( '-', $date_param );
		if ( count( $date_parts ) !== 3 ) {
			wp_send_json_error( 'Invalid date format' );
			return;
		}

		try {
			$checkin_date = new DateTime();
			$checkin_date->setDate( (int) $date_parts[2], (int) $date_parts[1], (int) $date_parts[0] );
		} catch ( Exception $e ) {
			wp_send_json_error( 'Invalid date' );
			return;
		}

		$result = $this->get_booking_periods_for_date( $property_id, $checkin_date );

		// Check if the arrival day has no eligible breaks (Sat, Sun, Thu).
		if ( isset( $result['no_breaks'] ) && $result['no_breaks'] ) {
			wp_send_json_success(
				array(
					'periods'        => array(),
					'no_breaks'      => true,
					'message'        => $result['message'],
					'checkin_date'   => $checkin_date->format( 'Y-m-d' ),
					'date_formatted' => $checkin_date->format( 'l, j F Y' ),
					'property_id'    => $property_id,
					'house_id'       => $house_post_id,
				)
			);
			return;
		}

		wp_send_json_success(
			array(
				'periods'        => $result,
				'checkin_date'   => $checkin_date->format( 'Y-m-d' ),
				'date_formatted' => $checkin_date->format( 'l, j F Y' ),
				'property_id'    => $property_id,
				'house_id'       => $house_post_id,
			)
		);
	}

	/**
	 * Get iPro PropertyId for a house from its ipro_property_id post meta.
	 *
	 * The ipro_property_id meta is the single source of truth, populated by the
	 * backfill CLI and the Blueprint at house creation. This replaces the previous
	 * property-mapping reflookup (post ID = PropertyReference), which depended on
	 * a live API call and on legacy post IDs matching PropertyReferences — an
	 * assumption that does not hold for Blueprint-created houses.
	 *
	 * @param int $wp_house_id WordPress post ID of the parent house.
	 * @return string|false iPro PropertyId, or false if the meta is not set.
	 */
	private function get_property_id_from_wp_house_id( $wp_house_id ) {
		$property_id = get_post_meta( (int) $wp_house_id, 'ipro_property_id', true );

		if ( '' === $property_id || null === $property_id ) {
			error_log( "No ipro_property_id set for WordPress house ID: {$wp_house_id}" );
			return false;
		}

		return (string) $property_id;
	}

	/**
	 * Get cached property mapping, fetch if not available.
	 *
	 * @return array|false Property mapping array or false on failure
	 */
	private function get_cached_property_mapping() {
		$transient_key = 'kt_property_mapping';

		// Try to get from cache first
		$cached_mapping = get_transient( $transient_key );
		if ( false !== $cached_mapping ) {
			return $cached_mapping;
		}

		// Fetch fresh data
		$mapping = $this->fetch_property_mapping();
		if ( $mapping ) {
			// Cache for 1 hour
			set_transient( $transient_key, $mapping, HOUR_IN_SECONDS );
		}

		return $mapping;
	}

	/**
	 * AJAX handler for booking enquiry submission.
	 */
	public function submit_booking_enquiry_callback() {
		// Verify nonce for security
		if ( ! wp_verify_nonce( $_POST['nonce'], 'house_booking_nonce' ) ) {
			wp_send_json_error( 'Invalid security token' );
			return;
		}

		// Sanitize all form fields using the field names expected by the iPro API
		$sanitized_data = array(
			// Basic guest information
			'first-name'         => sanitize_text_field( $_POST['first-name'] ?? '' ),
			'last-name'          => sanitize_text_field( $_POST['last-name'] ?? '' ),
			'email'              => sanitize_email( $_POST['email'] ?? '' ),
			'mobile'             => sanitize_text_field( $_POST['mobile'] ?? '' ),

			// Address fields
			'address-1'          => sanitize_text_field( $_POST['address-1'] ?? '' ),
			'address-2'          => sanitize_text_field( $_POST['address-2'] ?? '' ),
			'address-3'          => sanitize_text_field( $_POST['address-3'] ?? '' ),
			'address-4'          => sanitize_text_field( $_POST['address-4'] ?? '' ),
			'address-5'          => sanitize_text_field( $_POST['address-5'] ?? '' ),

			// Guest counts
			'number-of-adults'   => sanitize_text_field( $_POST['number-of-adults'] ?? '' ),
			'number-of-children' => sanitize_text_field( $_POST['number-of-children'] ?? '' ),
			'number-of-infants'  => sanitize_text_field( $_POST['number-of-infants'] ?? '' ),
			'number-of-pets'     => sanitize_text_field( $_POST['number-of-pets'] ?? '' ),

			// Pet details
			'breed-of-pets'      => sanitize_text_field( $_POST['breed-of-pets'] ?? '' ),
			'age-of-pets'        => sanitize_text_field( $_POST['age-of-pets'] ?? '' ),

			// Booking details
			'nature-of-stay'     => sanitize_text_field( $_POST['nature-of-stay'] ?? '' ),
			'age-range-from'     => sanitize_text_field( $_POST['age-range-from'] ?? '' ),
			'age-range-to'       => sanitize_text_field( $_POST['age-range-to'] ?? '' ),

			// Hidden fields for iPro compatibility
			'property_name'      => sanitize_text_field( $_POST['property_name'] ?? '' ),
			'date_from'          => sanitize_text_field( $_POST['date_from'] ?? '' ),
			'period'             => sanitize_text_field( $_POST['period'] ?? '' ),
			'post_id'            => sanitize_text_field( $_POST['house_id'] ?? $_POST['post_id'] ?? '' ),
			'salutation'         => sanitize_text_field( $_POST['salutation'] ?? '' ),

			// Additional fields
			'special_requests'   => sanitize_textarea_field( $_POST['special_requests'] ?? '' ),
			'how_heard'          => sanitize_text_field( $_POST['how_heard'] ?? '' ),
		);

		// Validate required fields
		$required_fields = array(
			'first-name'         => 'First Name',
			'last-name'          => 'Last Name',
			'email'              => 'Email Address',
			'mobile'             => 'Mobile Number',
			'address-1'          => 'Address Line 1',
			'address-3'          => 'Town/City',
			'address-5'          => 'Post Code',
			'number-of-adults'   => 'Number of Adults',
			'number-of-children' => 'Number of Children',
			'number-of-infants'  => 'Number of Infants',
			'nature-of-stay'     => 'Nature of Stay',
			'age-range-from'     => 'Age Range From',
			'age-range-to'       => 'Age Range To',
			'property_name'      => 'Property Name',
			'date_from'          => 'Check-in Date',
			'period'             => 'Booking Period',
			'post_id'            => 'House ID',
		);

		foreach ( $required_fields as $field => $label ) {
			if ( '' === $sanitized_data[ $field ] ) {
				wp_send_json_error( "Missing required field: {$label}" );
				return;
			}
		}

		// Validate email format
		if ( ! is_email( $sanitized_data['email'] ) ) {
			wp_send_json_error( 'Please enter a valid email address.' );
			return;
		}

		// Check if the Kate and Toms Get in Touch plugin is available
		if ( ! class_exists( 'Kate_And_Toms_Get_In_Touch_Public' ) ) {
			wp_send_json_error( 'Booking system is temporarily unavailable. Please try again later.' );
			return;
		}

		try {
			// Process the booking using the same logic as the existing callback
			// but without the redirect to work with AJAX
			$this->process_booking_for_ajax( $sanitized_data );

			// Generate a reference number for the response
			$reference_number = 'BK-' . strtoupper( wp_generate_password( 8, false ) );

			wp_send_json_success(
				array(
					'reference_number' => $reference_number,
					'house_name'       => $sanitized_data['property_name'],
					'guest_name'       => $sanitized_data['first-name'] . ' ' . $sanitized_data['last-name'],
					'message'          => 'Your booking enquiry has been submitted successfully!',
				)
			);

		} catch ( Exception $e ) {
			error_log( 'Booking submission error: ' . $e->getMessage() );
			wp_send_json_error( 'There was an error processing your booking. Please try again.' );
		}
	}

	/**
	 * Process booking data using the same logic as Kate and Toms Get in Touch plugin
	 * but adapted for AJAX responses (no redirect).
	 *
	 * @param array $post_data The sanitized booking data
	 * @throws Exception If processing fails
	 */
	private function process_booking_for_ajax( $post_data ) {
		// Anti-spam checks (same as original)
		if ( $post_data['email'] === 'testing@example.com' ) {
			throw new Exception( 'Spam detected' );
		}

		if ( strpos( $post_data['first-name'], 'Jcfuzqsq' ) !== false ) {
			throw new Exception( 'Spam detected' );
		}
		if ( strpos( $post_data['last-name'], 'Jcfuzqsq' ) !== false ) {
			throw new Exception( 'Spam detected' );
		}

		// Check if Get in Touch plugin methods are available
		if ( ! class_exists( 'Kate_And_Toms_Get_In_Touch_Public' ) ) {
			throw new Exception( 'Required plugin not available' );
		}

		$get_in_touch_public = new Kate_And_Toms_Get_In_Touch_Public( '', '' );

		// Use the plugin's helper methods if available
		$duration = $get_in_touch_public->resolve_booking_period( $post_data['period'] );
		$enddate  = date( 'Y-m-d', strtotime( $post_data['date_from'] . ' + ' . $duration . 'days' ) );

		// Resolve the iPro PropertyId from the ipro_property_id meta on the parent
		// house. This is the single source of truth, populated by the backfill CLI
		// and the Blueprint at house creation. The legacy availability_site_post_id
		// reflookup is intentionally NOT used: its references are stale and resolve
		// to wrong/withdrawn PropertyIds.
		$house_id    = (int) $post_data['post_id'];
		$property_id = get_post_meta( $house_id, 'ipro_property_id', true );

		if ( '' === $property_id || null === $property_id ) {
			throw new Exception( 'This house has no ipro_property_id configured; cannot submit the booking to the CRM.' );
		}

		$access_token = $get_in_touch_public->generate_access_token();

		// Build the same message format as original
		$message  = sprintf( __( 'Booking details are detailed below:' ) ) . "\r\n\r\n";
		$message .= sprintf( __( 'Full name: %s' ), $post_data['first-name'] . ' ' . $post_data['last-name'] ) . "\r\n\r\n";
		$message .= sprintf( __( 'Email address: %s' ), $post_data['email'] ) . "\r\n\r\n";
		$message .= sprintf( __( 'Mobile number: %s' ), $post_data['mobile'] ) . "\r\n\r\n";
		$message .= sprintf( __( 'Address: %s' ), $post_data['address-1'] . ' ' . $post_data['address-2'] . ' ' . $post_data['address-3'] . ' ' . $post_data['address-4'] . ' ' . $post_data['address-5'] ) . "\r\n\r\n";
		$message .= sprintf( __( 'Booking details:' ) ) . "\r\n\r\n";
		$message .= sprintf( __( 'House name: %s' ), $post_data['property_name'] ) . "\r\n\r\n";
		$message .= sprintf( __( '%1$s from %2$s' ), $post_data['period'], $post_data['date_from'] ) . "\r\n\r\n";
		$message .= sprintf( __( 'Number of Adults: %s' ), $post_data['number-of-adults'] ) . "\r\n\r\n";
		$message .= sprintf( __( 'Number of Children: %s' ), $post_data['number-of-children'] ) . "\r\n\r\n";
		$message .= sprintf( __( 'Number of Infants: %s' ), $post_data['number-of-infants'] ) . "\r\n\r\n";
		$message .= sprintf( __( 'Number of Pets: %s' ), $post_data['number-of-pets'] ) . "\r\n\r\n";
		$message .= sprintf( __( 'Breed of Pets: %s' ), $post_data['breed-of-pets'] ) . "\r\n\r\n";
		$message .= sprintf( __( 'Age of Pets: %s' ), $post_data['age-of-pets'] ) . "\r\n\r\n";
		$message .= sprintf( __( 'Nature of event: %s' ), $post_data['nature-of-stay'] ) . "\r\n\r\n";
		$message .= sprintf( __( 'Age range: %1$s - %2$s' ), $post_data['age-range-from'], $post_data['age-range-to'] ) . "\r\n\r\n";

		// Build iPro API URL (same as original)
		$api_url  = '/apis/enquiry?';
		$api_url .= 'firstname=' . urlencode( $post_data['first-name'] );
		$api_url .= '&lastname=' . urlencode( $post_data['last-name'] );
		$api_url .= '&startdate=' . date( 'Y-m-d', strtotime( $post_data['date_from'] ) );
		$api_url .= '&enddate=' . $enddate;
		$api_url .= '&days=' . $duration;
		$api_url .= '&mobile=' . urlencode( $post_data['mobile'] );
		$api_url .= '&phone=' . urlencode( '111111' );
		$api_url .= '&email=' . $post_data['email'];
		$api_url .= '&adults=' . urlencode( $post_data['number-of-adults'] );
		$api_url .= '&children=' . urlencode( $post_data['number-of-children'] );
		$api_url .= '&infants=' . urlencode( $post_data['number-of-infants'] );
		$api_url .= '&pets=' . urlencode( $post_data['number-of-pets'] );
		$api_url .= '&natureofstay=' . urlencode( $post_data['nature-of-stay'] );
		$api_url .= '&source=' . urlencode( 'Book Now' );
		$api_url .= '&propertyids=' . $property_id;
		$api_url .= '&comments=' . urlencode( $message );
		$api_url .= '&createdate=' . date( 'Y-m-d', strtotime( 'now' ) );

		// Make the iPro API call (same as original)
		$curl = curl_init();

		curl_setopt_array(
			$curl,
			array(
				CURLOPT_URL            => IPRO_API_URL . $api_url,
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_ENCODING       => '',
				CURLOPT_MAXREDIRS      => 10,
				CURLOPT_TIMEOUT        => 0,
				CURLOPT_FOLLOWLOCATION => true,
				CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
				CURLOPT_CUSTOMREQUEST  => 'POST',
				CURLOPT_HTTPHEADER     => array(
					'Content-Length: 0',
					'Authorization: Bearer ' . $access_token,
				),
			)
		);

		$response  = curl_exec( $curl );
		$http_code = curl_getinfo( $curl, CURLINFO_HTTP_CODE );
		curl_close( $curl );

		error_log( 'iPro API Response: ' . $response );

		$mail = (array) json_decode( $response );
		if ( ! array_key_exists( 'Id', $mail ) ) {
			$error_message = sprintf( __( 'Returned by iPRO API: %s' ), $response ) . "\r\n\r\n";
			$error_message = $message . $error_message;
			wp_mail( 'report@kateandtoms.com', 'Book Now Error via Kate & Toms', $error_message );
			wp_mail( 'elliott@squareonemd.co.uk', 'Book Now Error via Kate & Toms', $error_message );
			throw new Exception( 'iPro API error: ' . $response );
		}

		// Log successful submission
		error_log( 'Booking successfully submitted to iPro API. Response ID: ' . $mail['Id'] );
	}

	/**
	 * Store booking enquiry data (you might implement database storage here).
	 *
	 * @param array $booking_data The booking data to store
	 */
	private function store_booking_enquiry( $booking_data ) {
		// For now, just log to debug log if WP_DEBUG is enabled
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'Booking Enquiry Submitted: ' . print_r( $booking_data, true ) );
		}

		// TODO: You could implement database storage here if needed
		// For example, save to wp_postmeta or a custom table
	}

	/**
	 * Send booking notification email.
	 *
	 * @param array $booking_data The booking data
	 * @return bool True if email sent successfully
	 */
	private function send_booking_notification_email( $booking_data ) {
		$guest   = $booking_data['guest_details'];
		$booking = $booking_data['booking_details'];

		// Format the checkin date
		$checkin_formatted = DateTime::createFromFormat( 'd-m-Y', $booking_data['checkin_date'] );
		$checkin_display   = $checkin_formatted ? $checkin_formatted->format( 'l, j F Y' ) : $booking_data['checkin_date'];

		// Prepare email content
		$subject = 'New Booking Enquiry - ' . $booking_data['reference_number'];

		$message  = "New booking enquiry received:\n\n";
		$message .= 'Reference: ' . $booking_data['reference_number'] . "\n";
		$message .= 'House: ' . $booking_data['house_name'] . "\n";
		$message .= 'Period: ' . ucwords( str_replace( '-', ' ', $booking_data['selected_period'] ) ) . "\n";
		$message .= 'Check-in Date: ' . $checkin_display . "\n\n";

		$message .= "Guest Details:\n";
		$message .= 'Name: ' . $guest['first_name'] . ' ' . $guest['last_name'] . "\n";
		$message .= 'Email: ' . $guest['email'] . "\n";
		$message .= 'Phone: ' . $guest['phone'] . "\n";
		if ( ! empty( $guest['address'] ) ) {
			$message .= 'Address: ' . $guest['address'] . "\n";
		}
		$message .= "\n";

		$message .= "Booking Details:\n";
		$message .= 'Adults: ' . $booking['adults'] . "\n";
		$message .= 'Children: ' . $booking['children'] . "\n";
		if ( ! empty( $booking['special_requests'] ) ) {
			$message .= 'Special Requests: ' . $booking['special_requests'] . "\n";
		}
		if ( ! empty( $booking['how_heard'] ) ) {
			$message .= 'How they heard about us: ' . $booking['how_heard'] . "\n";
		}
		$message .= "\n";

		$message .= 'Submitted: ' . date( 'l, j F Y \a\t g:i A', $booking_data['submitted_at'] ) . "\n";
		$message .= 'IP Address: ' . $booking_data['ip_address'] . "\n";

		// Set email headers
		$headers = array(
			'Content-Type: text/plain; charset=UTF-8',
			'From: Kate & Toms <noreply@kateandtoms.com>',
			'Reply-To: ' . $guest['email'],
		);

		// Send to admin email
		$admin_email = get_option( 'admin_email', 'hello@kateandtoms.com' );
		$sent        = wp_mail( $admin_email, $subject, $message, $headers );

		// Send confirmation email to guest
		$this->send_guest_confirmation_email( $booking_data );

		return $sent;
	}

	/**
	 * Send confirmation email to the guest.
	 *
	 * @param array $booking_data The booking data
	 * @return bool True if email sent successfully
	 */
	private function send_guest_confirmation_email( $booking_data ) {
		$guest = $booking_data['guest_details'];

		// Format the checkin date
		$checkin_formatted = DateTime::createFromFormat( 'd-m-Y', $booking_data['checkin_date'] );
		$checkin_display   = $checkin_formatted ? $checkin_formatted->format( 'l, j F Y' ) : $booking_data['checkin_date'];

		$subject = 'Booking Enquiry Confirmation - ' . $booking_data['reference_number'];

		$message  = 'Dear ' . $guest['first_name'] . ",\n\n";
		$message .= "Thank you for your booking enquiry with Kate & Toms.\n\n";
		$message .= "We have received your request for:\n";
		$message .= '• House: ' . $booking_data['house_name'] . "\n";
		$message .= '• Period: ' . ucwords( str_replace( '-', ' ', $booking_data['selected_period'] ) ) . "\n";
		$message .= '• Check-in Date: ' . $checkin_display . "\n";
		$message .= '• Reference: ' . $booking_data['reference_number'] . "\n\n";

		$message .= "What happens next:\n";
		$message .= "• We'll review your request and check availability\n";
		$message .= "• You'll receive a confirmation email within 24 hours\n";
		$message .= "• If available, we'll send you booking terms and payment details\n";
		$message .= "• For urgent enquiries, please call us on 01242 235151\n\n";

		$message .= "If you have any questions, please don't hesitate to contact us:\n";
		$message .= "Phone: 01242 235151\n";
		$message .= "Email: hello@kateandtoms.com\n\n";

		$message .= "Thank you for choosing Kate & Toms.\n\n";
		$message .= "Best regards,\n";
		$message .= 'The Kate & Toms Team';

		$headers = array(
			'Content-Type: text/plain; charset=UTF-8',
			'From: Kate & Toms <hello@kateandtoms.com>',
		);

		return wp_mail( $guest['email'], $subject, $message, $headers );
	}

	/**
	 * Get client IP address.
	 *
	 * @return string Client IP address
	 */
	private function get_client_ip() {
		$ip_keys = array( 'HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR' );

		foreach ( $ip_keys as $key ) {
			if ( array_key_exists( $key, $_SERVER ) && ! empty( $_SERVER[ $key ] ) ) {
				$ip = sanitize_text_field( $_SERVER[ $key ] );
				if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
					return $ip;
				}
			}
		}

		return sanitize_text_field( $_SERVER['REMOTE_ADDR'] ?? 'Unknown' );
	}
}

/**
 * Filter houses by seasonal availability criteria.
 *
 * Checks if houses have availability for specific periods within a date range.
 * Uses the House_Calendar_Manager to check availability via the booking API.
 *
 * @param array  $houses Array of WP_Post objects (houses)
 * @param string $beginning_date Start date (Y-m-d format)
 * @param string $ending_date End date (Y-m-d format)
 * @param array  $periods Array of period keys to check (week, 2-night-weekend, etc.)
 * @return array Filtered array of houses that have availability
 */
function kate_toms_filter_houses_by_seasonal_availability( $houses, $beginning_date, $ending_date, $periods ) {
	if ( empty( $houses ) || ! is_array( $houses ) ) {
		return array();
	}

	// Get an instance of the calendar manager
	$calendar_manager = new House_Calendar_Manager();

	// Use reflection to access private methods
	$reflection             = new ReflectionClass( $calendar_manager );
	$get_property_id_method = $reflection->getMethod( 'get_property_id_from_wp_house_id' );
	$get_property_id_method->setAccessible( true );

	$filtered_houses      = array();
	$checked_count        = 0;
	$no_property_id_count = 0;

	foreach ( $houses as $house ) {
		// Get the iPro PropertyId for this house
		$property_id = $get_property_id_method->invoke( $calendar_manager, $house->ID );

		if ( ! $property_id ) {
			// Skip if no property ID mapping found
			++$no_property_id_count;
			continue;
		}

		++$checked_count;

		// Check if house has availability for ANY of the required periods within the date range
		if ( kate_toms_check_house_seasonal_availability( $property_id, $beginning_date, $ending_date, $periods ) ) {
			$filtered_houses[] = $house;
			error_log( sprintf( 'House ID %d (PropertyId %s) MATCHED seasonal criteria', $house->ID, $property_id ) );
		}
	}

	error_log(
		sprintf(
			'Seasonal filtering stats: Total houses=%d, Checked=%d, No PropertyId=%d, Matched=%d',
			count( $houses ),
			$checked_count,
			$no_property_id_count,
			count( $filtered_houses )
		)
	);

	return $filtered_houses;
}

/**
 * Check if a house has availability for the required periods within a date range.
 *
 * @param string $property_id iPro PropertyId
 * @param string $beginning_date Start date (Y-m-d format)
 * @param string $ending_date End date (Y-m-d format)
 * @param array  $periods Array of period keys to check
 * @return bool True if house has availability for at least one period
 */
function kate_toms_check_house_seasonal_availability( $property_id, $beginning_date, $ending_date, $periods ) {
	// Check cache first for this specific availability check
	$cache_key     = 'kt_seasonal_avail_' . $property_id . '_' . md5( $beginning_date . $ending_date . implode( ',', $periods ) );
	$cached_result = get_transient( $cache_key );

	if ( false !== $cached_result ) {
		return (bool) $cached_result;
	}

	$calendar_manager = new House_Calendar_Manager();

	// Use reflection to get the private access token
	$reflection     = new ReflectionClass( $calendar_manager );
	$token_property = $reflection->getProperty( 'api_access_token' );
	$token_property->setAccessible( true );
	$access_token = $token_property->getValue( $calendar_manager );

	// Get calendar data for this house with the access token (uses 20-minute cache)
	$calendar_data = $calendar_manager->get_calendar_data( $property_id, $access_token, false );

	if ( ! $calendar_data || ! isset( $calendar_data['availability'] ) ) {
		// Cache negative result for 10 minutes
		set_transient( $cache_key, 0, 10 * MINUTE_IN_SECONDS );
		return false;
	}

	// Convert dates to DateTime objects
	try {
		$start_date = new DateTime( $beginning_date );
		$end_date   = new DateTime( $ending_date );
	} catch ( Exception $e ) {
		return false;
	}

	// Use reflection to access the private method once
	$reflection         = new ReflectionClass( $calendar_manager );
	$get_periods_method = $reflection->getMethod( 'get_booking_periods_for_date' );
	$get_periods_method->setAccessible( true );

	// Only check key changeover days (Fridays=5 and Mondays=1) to optimize performance
	// This covers: weeks (Fri/Mon), weekends (Fri), midweeks (Mon)
	$current_date = clone $start_date;
	while ( $current_date <= $end_date ) {
		$day_of_week = (int) $current_date->format( 'N' ); // 1=Monday, 5=Friday

		// Only check Mondays and Fridays (main changeover days)
		if ( $day_of_week === 1 || $day_of_week === 5 ) {
			// Get available periods for this date
			$available_periods = $get_periods_method->invoke( $calendar_manager, $property_id, $current_date );

			if ( ! empty( $available_periods ) ) {
				// Check if any of the available periods match our required periods
				foreach ( $available_periods as $available_period ) {
					if ( in_array( $available_period['id'], $periods, true ) ) {
						// Found at least one matching period - this house qualifies
						// Cache positive result for 10 minutes
						set_transient( $cache_key, 1, 10 * MINUTE_IN_SECONDS );
						return true;
					}
				}
			}
		}

		// Move to next day
		$current_date->add( new DateInterval( 'P1D' ) );
	}

	// No matching periods found in the entire date range
	// Cache negative result for 10 minutes
	set_transient( $cache_key, 0, 10 * MINUTE_IN_SECONDS );
	return false;
}

/**
 * Get seasonal prices for display on availability landing pages.
 *
 * Returns an array of periods and their associated rates for the given date range.
 *
 * @param int    $house_id WordPress post ID for the house
 * @param string $beginning_date Start date (Y-m-d format)
 * @param string $ending_date End date (Y-m-d format)
 * @param array  $periods Array of period keys to check
 * @return array Associative array with period labels as keys and arrays of rates as values
 */
function kate_toms_get_seasonal_prices( $house_id, $beginning_date, $ending_date, $periods ) {
	// Check transient cache first for this specific request
	$cache_key     = 'kt_seasonal_prices_' . $house_id . '_' . md5( $beginning_date . $ending_date . implode( ',', $periods ) );
	$cached_prices = get_transient( $cache_key );

	if ( false !== $cached_prices ) {
		return $cached_prices;
	}

	$calendar_manager = new House_Calendar_Manager();

	// Use reflection to get the private property ID method
	$reflection             = new ReflectionClass( $calendar_manager );
	$get_property_id_method = $reflection->getMethod( 'get_property_id_from_wp_house_id' );
	$get_property_id_method->setAccessible( true );

	// Get the iPro PropertyId for this house
	$property_id = $get_property_id_method->invoke( $calendar_manager, $house_id );

	if ( ! $property_id ) {
		// Cache empty result to avoid repeated lookups
		set_transient( $cache_key, array(), 10 * MINUTE_IN_SECONDS );
		return array();
	}

	// Get access token using reflection
	$token_property = $reflection->getProperty( 'api_access_token' );
	$token_property->setAccessible( true );
	$access_token = $token_property->getValue( $calendar_manager );

	// Get calendar data for this house (uses its own 20-minute cache)
	$calendar_data = $calendar_manager->get_calendar_data( $property_id, $access_token, false );

	if ( ! $calendar_data || ! isset( $calendar_data['rates'] ) ) {
		// Cache empty result
		set_transient( $cache_key, array(), 10 * MINUTE_IN_SECONDS );
		return array();
	}

	// Map API period codes to display labels
	$period_labels = array(
		'week'            => 'Week',
		'2-night-weekend' => '2 night weekend',
		'3-night-weekend' => '3 night weekend',
		'midweek'         => 'Midweek',
		'2-night-midweek' => '2 night midweek',
	);

	// Map API codes to rate codes
	$stay_code_map = array(
		'week'            => '70',
		'2-night-weekend' => '50',
		'3-night-weekend' => '60',
		'midweek'         => '80',
		'2-night-midweek' => '85',
	);

	// Convert dates to DateTime objects
	try {
		$start_date = new DateTime( $beginning_date );
		$end_date   = new DateTime( $ending_date );
	} catch ( Exception $e ) {
		return array();
	}

	// Collect all rates for each period across the date range
	$available_dates = array();

	foreach ( $periods as $period_key ) {
		$stay_code    = $stay_code_map[ $period_key ] ?? null;
		$period_label = $period_labels[ $period_key ] ?? ucfirst( str_replace( '-', ' ', $period_key ) );

		if ( ! $stay_code ) {
			continue;
		}

		$available_dates[ $period_label ] = array();

		// Search through all rate periods in the calendar data
		foreach ( $calendar_data['rates'] as $month_rates ) {
			if ( ! isset( $month_rates['weeks'] ) ) {
				continue;
			}

			foreach ( $month_rates['weeks'] as $week_commencing => $week_rates ) {
				// Check if this week falls within our date range
				$week_date = new DateTime( $week_commencing );
				if ( $week_date < $start_date || $week_date > $end_date ) {
					continue;
				}

				// Check if this stay code has a rate for this week
				if ( isset( $week_rates[ $stay_code ] ) ) {
					$rate_data = $week_rates[ $stay_code ];

					// Convert rate data to display format
					if ( isset( $rate_data['value'] ) && $rate_data['value'] > 0 && 'price' === $rate_data['type'] ) {
						$rate_value = (string) $rate_data['value'];

						// Add offer indicator if present
						if ( isset( $rate_data['offer'] ) && $rate_data['offer'] > 0 ) {
							$rate_value .= str_repeat( '*', $rate_data['offer'] );
						}

						$available_dates[ $period_label ][] = $rate_value;
					} elseif ( 'from' === $rate_data['type'] ) {
						$available_dates[ $period_label ][] = '+'; // "from" indicator
					} elseif ( 'hidden' === $rate_data['type'] ) {
						$available_dates[ $period_label ][] = '-2'; // Hidden period
					}
				}
			}
		}
	}

	// Remove periods with no rates
	$available_dates = array_filter(
		$available_dates,
		function ( $rates ) {
			return ! empty( $rates );
		}
	);

	// Cache the result for 10 minutes
	set_transient( $cache_key, $available_dates, 10 * MINUTE_IN_SECONDS );

	return $available_dates;
}

/**
 * Determine whether a house has a special-offer rate within a date range.
 *
 * Offers are flagged in the rate strings produced by kate_toms_get_seasonal_prices()
 * with one or more '*' characters (see the 'offer' handling in that function). A house
 * "has an offer" if any rate for any of the requested periods, within the window,
 * carries that indicator.
 *
 * @param int    $house_id       WordPress post ID for the house.
 * @param string $beginning_date Start date (Y-m-d format).
 * @param string $ending_date    End date (Y-m-d format).
 * @param array  $periods        Array of period keys to check (API format).
 * @return bool True if at least one rate within the window carries an offer indicator.
 */
function kate_toms_house_has_seasonal_offer( $house_id, $beginning_date, $ending_date, $periods ) {
	$seasonal_prices = kate_toms_get_seasonal_prices( $house_id, $beginning_date, $ending_date, $periods );

	if ( empty( $seasonal_prices ) ) {
		return false;
	}

	foreach ( $seasonal_prices as $rates ) {
		foreach ( $rates as $rate ) {
			if ( false !== strpos( (string) $rate, '*' ) ) {
				return true;
			}
		}
	}

	return false;
}

/**
 * Convert array of price strings to minimum price formatted for display.
 *
 * Finds the minimum value from an array of price strings and formats it with comma separators.
 *
 * @param array $values Array of price values as strings
 * @return string Formatted minimum price with commas (e.g., "1,500")
 */
function kate_toms_convert_from_price( $values ) {
	if ( empty( $values ) || ! is_array( $values ) ) {
		return '0';
	}

	$int_values = array();
	foreach ( $values as $value ) {
		// Remove commas and convert to integer
		$number       = str_replace( ',', '', $value );
		$number       = (int) $number;
		$int_values[] = $number;
	}

	if ( empty( $int_values ) ) {
		return '0';
	}

	$min_value = min( $int_values );
	return number_format( $min_value );
}
