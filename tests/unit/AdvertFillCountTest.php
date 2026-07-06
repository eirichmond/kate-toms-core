<?php
/**
 * Unit tests for Kate_Toms_Special_Offers_Grid::advert_fill_count().
 *
 * Pure logic — no WordPress. The method takes the number of rendered cells and
 * returns how many advert placeholders complete the final row of four.
 *
 * @package kate-toms-core
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Covers the rows-of-four advert top-up maths.
 */
final class AdvertFillCountTest extends TestCase {

	/**
	 * A full row of four needs no adverts.
	 *
	 * @return void
	 */
	public function test_full_row_needs_no_fill(): void {
		$this->assertSame( 0, Kate_Toms_Special_Offers_Grid::advert_fill_count( 4 ) );
	}

	/**
	 * Five cards need three adverts to complete two rows.
	 *
	 * @return void
	 */
	public function test_five_cards_need_three(): void {
		$this->assertSame( 3, Kate_Toms_Special_Offers_Grid::advert_fill_count( 5 ) );
	}

	/**
	 * Eight cards (two full rows) need no adverts.
	 *
	 * @return void
	 */
	public function test_eight_cards_need_none(): void {
		$this->assertSame( 0, Kate_Toms_Special_Offers_Grid::advert_fill_count( 8 ) );
	}

	/**
	 * Counts for one, two and three cards top up to four.
	 *
	 * @return void
	 */
	public function test_partial_first_row(): void {
		$this->assertSame( 3, Kate_Toms_Special_Offers_Grid::advert_fill_count( 1 ) );
		$this->assertSame( 2, Kate_Toms_Special_Offers_Grid::advert_fill_count( 2 ) );
		$this->assertSame( 1, Kate_Toms_Special_Offers_Grid::advert_fill_count( 3 ) );
	}

	/**
	 * An empty grid needs no adverts.
	 *
	 * @return void
	 */
	public function test_empty_needs_none(): void {
		$this->assertSame( 0, Kate_Toms_Special_Offers_Grid::advert_fill_count( 0 ) );
	}

	/**
	 * A custom per-row value is honoured.
	 *
	 * @return void
	 */
	public function test_custom_per_row(): void {
		$this->assertSame( 1, Kate_Toms_Special_Offers_Grid::advert_fill_count( 2, 3 ) );
		$this->assertSame( 0, Kate_Toms_Special_Offers_Grid::advert_fill_count( 3, 3 ) );
	}

	/**
	 * A non-positive per-row value is treated as no fill (guard against divide-by-zero).
	 *
	 * @return void
	 */
	public function test_zero_per_row_is_safe(): void {
		$this->assertSame( 0, Kate_Toms_Special_Offers_Grid::advert_fill_count( 5, 0 ) );
	}
}
