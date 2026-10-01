<?php
/**
 * Tests for which stays count towards a seasonal landing page (#494).
 *
 * Calendars here are shaped like the processed House_Calendar_Manager data and
 * modelled on the New Year 2026 calendars Andy reported against.
 *
 * @package kate-toms-core
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * @covers Kate_Toms_Seasonal_Stays
 */
final class SeasonalStaysTest extends TestCase {

	/**
	 * Build processed calendar data.
	 *
	 * @param string[] $booked Booked dates (Y-m-d); every other day in the window is available.
	 * @param array    $rates  Rates keyed by week-commencing Friday, then rate code.
	 * @return array
	 */
	private function calendar( array $booked, array $rates ): array {
		$availability = array();

		for ( $day = new DateTimeImmutable( '2026-12-11' ); $day <= new DateTimeImmutable( '2027-01-31' ); $day = $day->modify( '+1 day' ) ) {
			$key                  = $day->format( 'Y-m-d' );
			$availability[ $key ] = array( 'status' => in_array( $key, $booked, true ) ? 'booked' : 'available' );
		}

		return array(
			'availability' => $availability,
			'rates'        => array( array( 'weeks' => $rates ) ),
		);
	}

	/**
	 * A published price rate.
	 *
	 * @param int $value Price in pounds.
	 * @param int $offer Number of offer stars.
	 * @return array
	 */
	private function price( int $value, int $offer = 0 ): array {
		return array(
			'type'  => 'price',
			'value' => $value,
			'offer' => $offer,
		);
	}

	/**
	 * Every date from one day to another, inclusive.
	 *
	 * @param string $from First date (Y-m-d).
	 * @param string $to   Last date (Y-m-d).
	 * @return string[]
	 */
	private function dates( string $from, string $to ): array {
		$dates = array();

		for ( $day = new DateTimeImmutable( $from ); $day <= new DateTimeImmutable( $to ); $day = $day->modify( '+1 day' ) ) {
			$dates[] = $day->format( 'Y-m-d' );
		}

		return $dates;
	}

	public function test_a_stay_arriving_inside_the_range_counts_even_if_it_ends_after_it(): void {
		// Marsden Manor: booked to Sun 27 Dec, free from Mon 28 Dec.
		$calendar = $this->calendar(
			$this->dates( '2026-12-18', '2026-12-27' ),
			array( '2026-12-25' => array( '70' => $this->price( 13375, 2 ) ) )
		);

		$stays = Kate_Toms_Seasonal_Stays::find( $calendar, '2026-12-28', '2027-01-01', array( 'week' ) );

		$this->assertCount( 1, $stays );
		$this->assertSame( '2026-12-28', $stays[0]['checkin'] );
		$this->assertSame( '2027-01-04', $stays[0]['checkout'] );
		$this->assertSame( 13375, $stays[0]['value'] );
		$this->assertSame( 2, $stays[0]['offer'] );
	}

	public function test_a_stay_arriving_before_the_range_does_not_count(): void {
		// Free week arriving Fri 25 Dec overlaps a range starting the 28th, but arrives before it.
		$calendar = $this->calendar(
			array( '2027-01-01', '2027-01-04' ),
			array( '2026-12-25' => array( '70' => $this->price( 9000 ) ) )
		);

		$this->assertSame( array(), Kate_Toms_Seasonal_Stays::find( $calendar, '2026-12-28', '2027-01-01', array( 'week' ) ) );
	}

	public function test_a_stay_wholly_after_the_range_does_not_count(): void {
		// Seaglass: booked through New Year, free again from Mon 4 Jan. A booked
		// Friday at the end of the range must not reach forward to the next week.
		$calendar = $this->calendar(
			$this->dates( '2026-12-28', '2027-01-03' ),
			array(
				'2027-01-01' => array(
					'70' => $this->price( 10425 ),
					'80' => $this->price( 5125 ),
				),
			)
		);

		$this->assertSame( array(), Kate_Toms_Seasonal_Stays::find( $calendar, '2026-12-28', '2027-01-01', array( 'week', 'midweek', '5-night' ) ) );
	}

	public function test_a_partly_booked_stay_does_not_count(): void {
		// Silvertree Manor: free Mon 28 and Tue 29 only, so no midweek or 5 nights fits.
		$calendar = $this->calendar(
			$this->dates( '2026-12-30', '2027-01-02' ),
			array(
				'2026-12-25' => array(
					'80' => $this->price( 5000 ),
					'90' => $this->price( 6000 ),
				),
			)
		);

		$this->assertSame( array(), Kate_Toms_Seasonal_Stays::find( $calendar, '2026-12-28', '2027-01-01', array( 'midweek', '5-night' ) ) );
	}

	public function test_prices_come_from_the_arrival_week_not_the_following_one(): void {
		// Range starting Sun 27 Dec: the midweek for Mon 4 Jan sits in the
		// rate week commencing Fri 1 Jan and must not be offered.
		$calendar = $this->calendar(
			array(),
			array(
				'2026-12-25' => array( '70' => $this->price( 13375 ) ),
				'2027-01-01' => array( '80' => $this->price( 3275 ) ),
			)
		);

		$stays = Kate_Toms_Seasonal_Stays::find( $calendar, '2026-12-27', '2027-01-01', array( 'week', 'midweek' ) );

		$this->assertSame( array( 'week' ), array_unique( array_column( $stays, 'period' ) ) );
		$this->assertSame( array( 13375 ), array_unique( array_column( $stays, 'value' ) ) );
	}

	public function test_unpriced_or_hidden_rates_do_not_count(): void {
		$calendar = $this->calendar(
			array(),
			array(
				'2026-12-25' => array(
					'70' => array(
						'type'  => 'hidden',
						'value' => null,
					),
					'90' => array(
						'type'  => 'no',
						'value' => null,
					),
				),
			)
		);

		$this->assertSame( array(), Kate_Toms_Seasonal_Stays::find( $calendar, '2026-12-28', '2027-01-01', array( 'week', '5-night' ) ) );
	}

	public function test_only_requested_periods_and_their_arrival_days_are_used(): void {
		$calendar = $this->calendar(
			array(),
			array(
				'2026-12-25' => array(
					'50' => $this->price( 3000 ),
					'90' => $this->price( 6000 ),
				),
			)
		);

		$stays = Kate_Toms_Seasonal_Stays::find( $calendar, '2026-12-25', '2026-12-31', array( '2-night-weekend' ) );

		// Weekends arrive on a Friday only.
		$this->assertSame( array( '2026-12-25' ), array_column( $stays, 'checkin' ) );
	}

	public function test_week_commencing_is_the_friday_on_or_before_arrival(): void {
		$this->assertSame( '2026-12-25', Kate_Toms_Seasonal_Stays::week_commencing( new DateTimeImmutable( '2026-12-25' ) ) );
		$this->assertSame( '2026-12-25', Kate_Toms_Seasonal_Stays::week_commencing( new DateTimeImmutable( '2026-12-28' ) ) );
		$this->assertSame( '2026-12-25', Kate_Toms_Seasonal_Stays::week_commencing( new DateTimeImmutable( '2026-12-31' ) ) );
		$this->assertSame( '2027-01-01', Kate_Toms_Seasonal_Stays::week_commencing( new DateTimeImmutable( '2027-01-03' ) ) );
	}
}
