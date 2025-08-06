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
		
		// Lightweight AJAX handler for just availability notes
		add_action( 'wp_ajax_get_availability_notes', array( $this, 'get_availability_notes_callback' ) );
		add_action( 'wp_ajax_nopriv_get_availability_notes', array( $this, 'get_availability_notes_callback' ) );
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
							// Fix sandwiched available days before processing
							//$days = $this->fix_sandwiched_available_days( $days );
							
							foreach ( $days as $day => $status ) {
								$date = sprintf( '%04d-%02d-%02d', $year, $month, $day );
								$processed_data['availability'][$date] = $this->process_day_availability( $date, $status );
							}
						}
					}
				}
			}
		}
		
		// Preserve AvailabilityNotes from raw rates data
		if ( isset( $rates['AvailabilityNotes'] ) ) {
			$processed_data['AvailabilityNotes'] = $rates['AvailabilityNotes'];
		}
		
		// Process rates with Kate & Tom's pricing keys
		if ( isset( $rates['Rates'] ) && is_array( $rates['Rates'] ) ) {
			foreach ( $rates['Rates'] as $rate_period ) {
				$month_key = $rate_period['Month'];
				$processed_month_rates = $this->process_kt_rates( $rate_period, $processed_data['availability'] );
				
				// Only store if not null (i.e., not a past month)
				if ( null !== $processed_month_rates ) {
					$processed_data['rates'][$month_key] = $processed_month_rates;
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
			'U' => 'booked',
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

		// TEMPORARY DEBUG: only process August 2025 dates
		// if ( strpos( $date, '2025-08' ) === false ) {
		// 	return $processed_day;
		// } 
		// if ( '2025-08-03' != $date ) {
		// 	return $processed_day;
		// } 

		$day_of_week = (int) $processed_day['day_of_week'];
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
		if ( in_array( $day_of_week, array( 7, 1, 5 ), true ) && 'available' === $status ) {
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
	 * note:
	 * 	2 night weekend = Friday(checkin day) +1day checkout Sunday
	 * 	3 night weekend = Friday(checkin day) +2day checkout Monday
	 * 	Week = Friday(checkin day) +6day checkout Friday || Monday(also checkin day) +6day checkout Monday
	 * 	Midweek = Monday(checkin day) +3day checkout Friday
	 * 	2 night midweek = Monday, Tues or Weds(checkin days) +1day checkout +1day from checkin
	 *
	 * @param array $rate_period Rate data for a specific month.
	 * @param array $availability_data Processed availability data for validation.
	 * @return array Processed rates data.
	 */
	private function process_kt_rates( $rate_period, $availability_data = array() ) {
		// Skip processing if entire month is in the past
		$month_key = $rate_period['Month'];
		$month_timestamp = strtotime( $month_key . '-01' ); // Add day to make it a valid date
		$current_month_timestamp = strtotime( gmdate( 'Y-m-01' ) ); // First day of current month
		
		if ( $month_timestamp < $current_month_timestamp ) {
			return null; // Don't process past months
		}
		
		$processed_rates = array(
			'month' => $rate_period['Month'],
			'notes' => $rate_period['Notes'] ?? '',
			'weeks' => array(),
		);
		
		foreach ( $rate_period['WeekPriceList'] as $week_data ) {
			$week_commencing = $week_data['WeekCommencing'];
			
			// Skip processing if week is more than 7 days in the past
			$week_timestamp = strtotime( $week_commencing );
			$seven_days_ago = strtotime( '-7 days' );
			if ( $week_timestamp < $seven_days_ago ) {
				continue;
			}
			
			$processed_rates['weeks'][$week_commencing] = array();
			
			
			foreach ( $week_data['Amount'] as $stay_code => $rate ) {
				$processed_rate = $this->process_single_rate( $rate );
				
				// Only validate rates that have actual pricing data
				$should_validate = in_array( $processed_rate['type'], array( 'price', 'from', 'special1', 'special2', 'special3', 'previous' ), true );
				
				if ( $should_validate && ! empty( $availability_data ) ) {
					// Validate rate against availability data
					if ( $this->is_booking_period_available( $week_commencing, $stay_code, $availability_data ) ) {
						$processed_rates['weeks'][$week_commencing][$stay_code] = $processed_rate;
						
						// Debug logging
						if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
							error_log( "Rate validation PASSED: Week {$week_commencing}, Stay {$stay_code}, Type {$processed_rate['type']}" );
						}
					} else {
						// Override with unavailable if booking period isn't free
						$processed_rates['weeks'][$week_commencing][$stay_code] = array(
							'type' => 'unavailable',
							'display' => 'Booked',
							'value' => null,
						);
						
						// Debug logging
						if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
							error_log( "Rate validation FAILED: Week {$week_commencing}, Stay {$stay_code}, Original type {$processed_rate['type']} -> Marked as Booked" );
						}
					}
				} else {
					// Keep original processed rate (already unavailable, hidden, etc. or no availability data)
					$processed_rates['weeks'][$week_commencing][$stay_code] = $processed_rate;
					
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
					return array( 'type' => 'no', 'display' => 'Booked', 'value' => null );
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
		
		return array( 'type' => 'unavailable', 'display' => 'Booked', 'value' => null );
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
			'50' => array( 'nights' => 2, 'checkin_days' => array( 5 ) ),         // 2-night weekend: Friday
			'60' => array( 'nights' => 3, 'checkin_days' => array( 5 ) ),         // 3-night weekend: Friday
			'70' => array( 'nights' => 7, 'checkin_days' => array( 5, 1 ) ),      // Week: Friday OR Monday
			'80' => array( 'nights' => 4, 'checkin_days' => array( 1 ) ),         // Midweek: Monday
			'85' => array( 'nights' => 2, 'checkin_days' => array( 1, 2, 3 ) ),   // 2-night midweek: Monday, Tuesday, Wednesday
			'90' => array( 'nights' => 5, 'checkin_days' => array( 5 ) ),         // 5 nights: Friday
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
		$week_friday = strtotime( $week_commencing );
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
			
			$checkin_date = gmdate( 'Y-m-d', $week_friday + ( $offset * DAY_IN_SECONDS ) );
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
	 * Fix sandwiched available days - convert single "A" days between booked days to "B".
	 *
	 * @param array $days Array of day => status for a month.
	 * @return array Modified days array.
	 */
	private function fix_sandwiched_available_days( $days ) {
		if ( ! is_array( $days ) || count( $days ) < 3 ) {
			return $days;
		}

		$day_keys = array_keys( $days );
		$unavailable_statuses = array( 'B', 'OB', 'U' );

		// Loop through days looking for sandwiched "A" values
		for ( $i = 1; $i < count( $day_keys ) - 1; $i++ ) {
			$prev_key = $day_keys[ $i - 1 ];
			$current_key = $day_keys[ $i ];
			$next_key = $day_keys[ $i + 1 ];

			// Check if current day is "A" and surrounded by unavailable days
			if ( 
				'A' === $days[ $current_key ] &&
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
}
