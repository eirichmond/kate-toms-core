<?php
/**
 * Tests for the landing page location tax_query builder.
 *
 * @package kate-toms-core
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * @covers Kate_Toms_Location_Tax_Query
 */
final class LocationTaxQueryTest extends TestCase {

	/**
	 * The four broad region term IDs (Cotswolds, Coast, Country, Town).
	 *
	 * @var int[]
	 */
	private const REGIONS = array( 604, 810, 790, 603 );

	/**
	 * Pull the terms out of the clause for the given operator/position.
	 *
	 * @param array[] $clauses Clauses returned by the builder.
	 * @return array[] List of [terms] per clause.
	 */
	private function terms_of( array $clauses ): array {
		return array_map(
			static function ( array $clause ): array {
				return $clause['terms'];
			},
			$clauses
		);
	}

	public function test_no_locations_produces_no_clauses(): void {
		$this->assertSame( array(), Kate_Toms_Location_Tax_Query::build( array(), self::REGIONS ) );
	}

	public function test_region_only_produces_a_single_in_clause(): void {
		$clauses = Kate_Toms_Location_Tax_Query::build( array( 810 ), self::REGIONS );

		$this->assertCount( 1, $clauses );
		$this->assertSame( 'location', $clauses[0]['taxonomy'] );
		$this->assertSame( 'term_id', $clauses[0]['field'] );
		$this->assertSame( 'IN', $clauses[0]['operator'] );
		$this->assertSame( array( 810 ), $clauses[0]['terms'] );
	}

	/**
	 * The case the AND operator was originally introduced for: a broad region
	 * plus one granular location must still require both.
	 */
	public function test_region_plus_single_granular_still_requires_both(): void {
		$clauses = Kate_Toms_Location_Tax_Query::build( array( 790, 4903 ), self::REGIONS );

		$this->assertCount( 2, $clauses );
		$this->assertSame( array( array( 790 ), array( 4903 ) ), $this->terms_of( $clauses ) );

		foreach ( $clauses as $clause ) {
			$this->assertSame( 'IN', $clause['operator'] );
		}
	}

	/**
	 * BugHerd #432 — /party-houses/london/. The granular locations are
	 * alternatives, so they must share one IN clause rather than being ANDed
	 * together, which asked for a house in seven counties at once.
	 */
	public function test_london_section_ors_the_granular_locations(): void {
		$clauses = Kate_Toms_Location_Tax_Query::build(
			array( 603, 4903, 4375, 1148, 5520, 4380, 4374, 1345 ),
			self::REGIONS
		);

		$this->assertCount( 2, $clauses );
		$this->assertSame( array( 603 ), $clauses[0]['terms'] );
		$this->assertSame( array( 4903, 4375, 1148, 5520, 4380, 4374, 1345 ), $clauses[1]['terms'] );
		$this->assertSame( 'IN', $clauses[1]['operator'] );
	}

	/**
	 * BugHerd #433 — /party-houses/wales/. Same shape: Coast + [Conwy, Newquay,
	 * Wales]. Scholar's Hall is in Coast and Wales but not Conwy or Newquay, so
	 * ANDing the granular terms excluded it.
	 */
	public function test_wales_section_ors_the_granular_locations(): void {
		$clauses = Kate_Toms_Location_Tax_Query::build( array( 810, 5789, 4382, 62 ), self::REGIONS );

		$this->assertCount( 2, $clauses );
		$this->assertSame( array( 810 ), $clauses[0]['terms'] );
		$this->assertSame( array( 5789, 4382, 62 ), $clauses[1]['terms'] );
	}

	public function test_granular_only_produces_a_single_in_clause(): void {
		$clauses = Kate_Toms_Location_Tax_Query::build( array( 62, 5789 ), self::REGIONS );

		$this->assertCount( 1, $clauses );
		$this->assertSame( array( 62, 5789 ), $clauses[0]['terms'] );
		$this->assertSame( 'IN', $clauses[0]['operator'] );
	}

	public function test_duplicate_and_empty_terms_are_discarded(): void {
		$clauses = Kate_Toms_Location_Tax_Query::build( array( 810, 810, 0, 62, 62 ), self::REGIONS );

		$this->assertSame( array( array( 810 ), array( 62 ) ), $this->terms_of( $clauses ) );
	}

	public function test_numeric_strings_are_normalised_to_ints(): void {
		$clauses = Kate_Toms_Location_Tax_Query::build( array( '810', '62' ), self::REGIONS );

		$this->assertSame( array( array( 810 ), array( 62 ) ), $this->terms_of( $clauses ) );
	}
}
