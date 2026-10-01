<?php
/**
 * The bookable stays a house has inside a seasonal date range.
 *
 * Seasonal and availability landing pages ask "which houses have a week /
 * weekend / 5 nights free in this range, and from what price". Both halves of
 * that answer must come from the same stays, and every stay must really sit in
 * the range: it arrives on or between the beginning and ending dates, every
 * night of it is free, and the house has published a price for it.
 *
 * This used to be answered two different ways. Inclusion asked the booking
 * period lookup with its "nearby dates" fallback switched on, so a booked
 * Friday on the last day of the range searched forward and found the next
 * week (Seaglass at New Year, #494). Prices were read per rate week by its
 * week-commencing Friday, so a range ending on a Friday picked up that whole
 * following week's rates (Marsden's midweek for Mon 4 Jan) and missed the
 * week that actually arrives in the range.
 *
 * Pure PHP with no WordPress dependency, so it can be unit tested directly.
 *
 * @package Kate_Toms_Core
 */

if ( ! class_exists( 'Kate_Toms_Seasonal_Stays' ) ) {

	/**
	 * Lists the in-range, available, priced stays from processed calendar data.
	 */
	class Kate_Toms_Seasonal_Stays {

		/**
		 * Stay rules per period key: rate code, nights, and arrival days (ISO-8601, 1 = Monday).
		 *
		 * Mirrors the arrival rules in House_Calendar_Manager::get_booking_periods_for_date().
		 *
		 * @var array
		 */
		public const PERIODS = array(
			'2-night-weekend' => array(
				'code'         => '50',
				'nights'       => 2,
				'arrival_days' => array( 5 ),
			),
			'3-night-weekend' => array(
				'code'         => '60',
				'nights'       => 3,
				'arrival_days' => array( 5 ),
			),
			'week'            => array(
				'code'         => '70',
				'nights'       => 7,
				'arrival_days' => array( 5, 1 ),
			),
			'midweek'         => array(
				'code'         => '80',
				'nights'       => 4,
				'arrival_days' => array( 1 ),
			),
			'2-night-midweek' => array(
				'code'         => '85',
				'nights'       => 2,
				'arrival_days' => array( 1, 2, 3 ),
			),
			'5-night'         => array(
				'code'         => '90',
				'nights'       => 5,
				'arrival_days' => array( 1, 2, 3, 4, 5, 6, 7 ),
			),
		);

		/**
		 * Find every bookable stay of the given periods arriving within the range.
		 *
		 * @param array  $calendar_data  Processed calendar data, with 'availability'
		 *                               (keyed Y-m-d) and 'rates' (months of 'weeks'
		 *                               keyed by week-commencing Friday).
		 * @param string $beginning_date First allowed arrival date (Y-m-d).
		 * @param string $ending_date    Last allowed arrival date (Y-m-d), inclusive.
		 * @param array  $periods        Period keys, e.g. array( 'week', '5-night' ).
		 * @return array[] Stays in arrival order, each with period, checkin, checkout,
		 *                 value, offer (number of offer stars) and from (bool).
		 */
		public static function find( array $calendar_data, $beginning_date, $ending_date, array $periods ) {
			if ( empty( $calendar_data['availability'] ) || empty( $calendar_data['rates'] ) ) {
				return array();
			}

			try {
				$arrival = new DateTimeImmutable( $beginning_date );
				$last    = new DateTimeImmutable( $ending_date );
			} catch ( Exception $e ) {
				return array();
			}

			$rates = self::index_rates( $calendar_data['rates'] );
			$stays = array();

			for ( ; $arrival <= $last; $arrival = $arrival->modify( '+1 day' ) ) {
				$day = (int) $arrival->format( 'N' );

				foreach ( $periods as $period_key ) {
					$rule = self::PERIODS[ $period_key ] ?? null;

					if ( ! $rule || ! in_array( $day, $rule['arrival_days'], true ) ) {
						continue;
					}

					if ( ! self::nights_are_free( $arrival, $rule['nights'], $calendar_data['availability'] ) ) {
						continue;
					}

					$rate = $rates[ self::week_commencing( $arrival ) ][ $rule['code'] ] ?? null;

					if ( ! is_array( $rate ) || 'price' !== ( $rate['type'] ?? '' ) || empty( $rate['value'] ) || $rate['value'] <= 0 ) {
						continue;
					}

					$stays[] = array(
						'period'   => $period_key,
						'checkin'  => $arrival->format( 'Y-m-d' ),
						'checkout' => $arrival->modify( '+' . $rule['nights'] . ' days' )->format( 'Y-m-d' ),
						'value'    => (int) $rate['value'],
						'offer'    => (int) ( $rate['offer'] ?? 0 ),
						'from'     => ! empty( $rate['from'] ),
					);
				}
			}

			return $stays;
		}

		/**
		 * The rate week a stay is priced from: the Friday on or before arrival.
		 *
		 * @param DateTimeImmutable $arrival Arrival date.
		 * @return string Week-commencing date (Y-m-d).
		 */
		public static function week_commencing( DateTimeImmutable $arrival ) {
			$days_back = ( (int) $arrival->format( 'N' ) + 2 ) % 7; // Fri 0, Sat 1 ... Thu 6.

			return $arrival->modify( '-' . $days_back . ' days' )->format( 'Y-m-d' );
		}

		/**
		 * Whether every night of a stay is marked available.
		 *
		 * @param DateTimeImmutable $arrival      Arrival date.
		 * @param int               $nights       Number of nights.
		 * @param array             $availability Availability keyed by Y-m-d.
		 * @return bool
		 */
		private static function nights_are_free( DateTimeImmutable $arrival, $nights, array $availability ) {
			for ( $i = 0; $i < $nights; $i++ ) {
				$night = $arrival->modify( '+' . $i . ' days' )->format( 'Y-m-d' );

				if ( 'available' !== ( $availability[ $night ]['status'] ?? null ) ) {
					return false;
				}
			}

			return true;
		}

		/**
		 * Flatten the monthly rate lists into one lookup by week-commencing date.
		 *
		 * @param array $rate_months Processed rate months.
		 * @return array Rates keyed by week-commencing date, then rate code.
		 */
		private static function index_rates( array $rate_months ) {
			$index = array();

			foreach ( $rate_months as $month ) {
				if ( ! is_array( $month ) || empty( $month['weeks'] ) ) {
					continue;
				}

				foreach ( $month['weeks'] as $week_commencing => $week_rates ) {
					$index[ $week_commencing ] = $week_rates;
				}
			}

			return $index;
		}
	}
}
