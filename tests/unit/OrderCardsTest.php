<?php
/**
 * Unit tests for Kate_Toms_Special_Offers_Grid::order_cards().
 *
 * Pure logic — no WordPress. "Now" is fixed at 2026-07-03 for deterministic
 * expiry boundaries.
 *
 * @package kate-toms-core
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Covers the ordering, expiry filtering, and trailing-bucket rules.
 */
final class OrderCardsTest extends TestCase {

	/**
	 * Fixed "today" used across the suite (site-timezone moment).
	 *
	 * @return \DateTimeImmutable The 3rd of July 2026.
	 */
	private function now(): \DateTimeImmutable {
		return new \DateTimeImmutable( '2026-07-03' );
	}

	/**
	 * Reduces a result list to its selected post IDs for concise assertions.
	 *
	 * @param array<int, array<string, mixed>> $cards Result cards.
	 *
	 * @return array<int, int> Ordered post IDs.
	 */
	private function ids( array $cards ): array {
		return array_map(
			static fn( array $card ): int => (int) $card['selectedPostId'],
			$cards
		);
	}

	/**
	 * Dated house cards are ordered soonest-expiry-first regardless of input order.
	 *
	 * @return void
	 */
	public function test_sorts_dated_houses_ascending(): void {
		$children = array(
			array(
				'selectedPostId' => 1,
				'offerDate'      => '2026-09-10',
			),
			array(
				'selectedPostId' => 2,
				'offerDate'      => '2026-07-15',
			),
			array(
				'selectedPostId' => 3,
				'offerDate'      => '2026-08-01',
			),
		);

		$result = Kate_Toms_Special_Offers_Grid::order_cards( $children, $this->now() );

		$this->assertSame( array( 2, 3, 1 ), $this->ids( $result ) );
	}

	/**
	 * Offers whose date is before today are excluded from the render list.
	 *
	 * @return void
	 */
	public function test_excludes_expired_offers(): void {
		$children = array(
			array(
				'selectedPostId' => 1,
				'offerDate'      => '2026-06-01',
			),
			array(
				'selectedPostId' => 2,
				'offerDate'      => '2026-07-15',
			),
		);

		$result = Kate_Toms_Special_Offers_Grid::order_cards( $children, $this->now() );

		$this->assertSame( array( 2 ), $this->ids( $result ) );
	}

	/**
	 * An offer expiring today is still shown (today is inclusive).
	 *
	 * @return void
	 */
	public function test_keeps_offer_expiring_today(): void {
		$children = array(
			array(
				'selectedPostId' => 1,
				'offerDate'      => '2026-07-03',
			),
		);

		$result = Kate_Toms_Special_Offers_Grid::order_cards( $children, $this->now() );

		$this->assertSame( array( 1 ), $this->ids( $result ) );
	}

	/**
	 * Time/zone suffixes on the stored date do not shift the calendar date.
	 *
	 * @return void
	 */
	public function test_datetime_suffix_does_not_shift_expiry(): void {
		$children = array(
			array(
				'selectedPostId' => 1,
				'offerDate'      => '2026-07-03T00:00:00',
			),
		);

		$result = Kate_Toms_Special_Offers_Grid::order_cards( $children, $this->now() );

		$this->assertSame( array( 1 ), $this->ids( $result ) );
	}

	/**
	 * Dateless houses, invalid dates, and placeholders trail dated cards in stable input order.
	 *
	 * @return void
	 */
	public function test_dateless_and_placeholders_trail_in_stable_order(): void {
		$children = array(
			array(
				'selectedPostId' => 10,
				'offerDate'      => '2026-08-01',
			),
			array(
				'isPlaceholder'       => true,
				'placeholderLocation' => 'coast',
			),
			array( 'selectedPostId' => 20 ),                                  // dateless.
			array(
				'selectedPostId' => 30,
				'offerDate'      => '2026-13-40',
			),     // invalid -> dateless.
			array(
				'selectedPostId' => 40,
				'offerDate'      => '2026-07-15',
			),
		);

		$result = Kate_Toms_Special_Offers_Grid::order_cards( $children, $this->now() );

		// Dated houses first (soonest first: 40 then 10), then trailing in input order.
		$this->assertSame( array( 40, 10, 0, 20, 30 ), $this->ids( $result ) );
		$this->assertSame( Kate_Toms_Special_Offers_Grid::TYPE_HOUSE, $result[0]['type'] );
		$this->assertSame( Kate_Toms_Special_Offers_Grid::TYPE_PLACEHOLDER, $result[2]['type'] );
		$this->assertSame( 'coast', $result[2]['placeholderLocation'] );
	}

	/**
	 * Empty input yields an empty render list.
	 *
	 * @return void
	 */
	public function test_empty_input_returns_empty(): void {
		$this->assertSame( array(), Kate_Toms_Special_Offers_Grid::order_cards( array(), $this->now() ) );
	}

	/**
	 * All-placeholder input is preserved in order and never date-filtered.
	 *
	 * @return void
	 */
	public function test_all_placeholder_input_preserved(): void {
		$children = array(
			array(
				'isPlaceholder'       => true,
				'placeholderLocation' => 'town',
			),
			array(
				'isPlaceholder'       => true,
				'placeholderLocation' => 'coast',
			),
		);

		$result = Kate_Toms_Special_Offers_Grid::order_cards( $children, $this->now() );

		$this->assertCount( 2, $result );
		$this->assertSame( 'town', $result[0]['placeholderLocation'] );
		$this->assertSame( 'coast', $result[1]['placeholderLocation'] );
	}

	/**
	 * Equal offer dates preserve the original relative order (stable sort).
	 *
	 * @return void
	 */
	public function test_equal_dates_are_stable(): void {
		$children = array(
			array(
				'selectedPostId' => 1,
				'offerDate'      => '2026-08-01',
			),
			array(
				'selectedPostId' => 2,
				'offerDate'      => '2026-08-01',
			),
			array(
				'selectedPostId' => 3,
				'offerDate'      => '2026-08-01',
			),
		);

		$result = Kate_Toms_Special_Offers_Grid::order_cards( $children, $this->now() );

		$this->assertSame( array( 1, 2, 3 ), $this->ids( $result ) );
	}
}
