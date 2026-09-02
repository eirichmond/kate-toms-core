<?php
/**
 * Tests for the landing page section tax_query builder.
 *
 * @package kate-toms-core
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * @covers Kate_Toms_House_Tax_Query
 */
final class HouseTaxQueryTest extends TestCase {

	/**
	 * The four broad region term IDs (Cotswolds, Coast, Country, Town).
	 *
	 * @var int[]
	 */
	private const REGIONS = array( 604, 810, 790, 603 );

	/**
	 * Build with the standard region list.
	 *
	 * @param array $term_ids Selected term IDs keyed by taxonomy.
	 * @param array $logic    Relation keyed by taxonomy.
	 * @return array The tax_query.
	 */
	private function build( array $term_ids, array $logic = array() ): array {
		return Kate_Toms_House_Tax_Query::build( $term_ids, $logic, self::REGIONS );
	}

	/**
	 * Find the clause for a taxonomy.
	 *
	 * @param array  $tax_query The built tax_query.
	 * @param string $taxonomy  Taxonomy to look for.
	 * @return array|null The clause, or null when absent.
	 */
	private function clause_for( array $tax_query, string $taxonomy ): ?array {
		foreach ( $tax_query as $key => $clause ) {
			if ( 'relation' === $key || ! is_array( $clause ) ) {
				continue;
			}

			if ( $taxonomy === ( $clause['taxonomy'] ?? null ) ) {
				return $clause;
			}
		}

		return null;
	}

	public function test_nothing_selected_produces_an_empty_query(): void {
		$this->assertSame( array(), $this->build( array() ) );
	}

	public function test_single_taxonomy_carries_no_relation_key(): void {
		$tax_query = $this->build( array( 'feature' => array( 12, 34 ) ) );

		$this->assertArrayNotHasKey( 'relation', $tax_query );
		$this->assertCount( 1, $tax_query );
	}

	public function test_taxonomies_are_anded_together(): void {
		$tax_query = $this->build(
			array(
				'feature' => array( 12 ),
				'size'    => array( 56 ),
			)
		);

		$this->assertSame( 'AND', $tax_query['relation'] );
	}

	public function test_multiple_terms_default_to_matching_any(): void {
		$clause = $this->clause_for( $this->build( array( 'feature' => array( 12, 34 ) ) ), 'feature' );

		$this->assertSame( 'IN', $clause['operator'] );
		$this->assertSame( array( 12, 34 ), $clause['terms'] );
	}

	/**
	 * The ticket's example: Hen Party Houses AND Party House with a Hot Tub,
	 * both `feature` terms, must require both rather than either.
	 */
	public function test_and_logic_requires_every_selected_term(): void {
		$clause = $this->clause_for(
			$this->build(
				array( 'feature' => array( 12, 34 ) ),
				array( 'feature' => 'AND' )
			),
			'feature'
		);

		$this->assertSame( 'AND', $clause['operator'] );
		$this->assertSame( array( 12, 34 ), $clause['terms'] );
	}

	public function test_logic_applies_only_to_the_named_taxonomy(): void {
		$tax_query = $this->build(
			array(
				'feature' => array( 12, 34 ),
				'size'    => array( 56, 78 ),
			),
			array( 'feature' => 'AND' )
		);

		$this->assertSame( 'AND', $this->clause_for( $tax_query, 'feature' )['operator'] );
		$this->assertSame( 'IN', $this->clause_for( $tax_query, 'size' )['operator'] );
	}

	/**
	 * Sections saved before the relation existed carry no logic at all, and
	 * must keep returning exactly what they returned before.
	 */
	public function test_absent_logic_reproduces_the_previous_behaviour(): void {
		$term_ids = array(
			'location' => array( 810, 900 ),
			'feature'  => array( 12, 34 ),
			'size'     => array( 56 ),
			'type'     => array( 78 ),
			'occasion' => array( 90 ),
		);

		foreach ( $this->build( $term_ids ) as $key => $clause ) {
			if ( 'relation' === $key ) {
				continue;
			}

			$this->assertSame( 'IN', $clause['operator'] );
		}
	}

	public function test_location_keeps_its_region_and_granular_split(): void {
		$tax_query = $this->build(
			array( 'location' => array( 810, 900, 901 ) ),
			// Location is not configurable; passing a relation must not change it.
			array( 'location' => 'AND' )
		);

		$this->assertCount( 3, $tax_query, 'Two location clauses plus the relation key.' );
		$this->assertSame( array( 810 ), $tax_query[0]['terms'] );
		$this->assertSame( array( 900, 901 ), $tax_query[1]['terms'] );
		$this->assertSame( 'IN', $tax_query[0]['operator'] );
		$this->assertSame( 'IN', $tax_query[1]['operator'] );
	}

	public function test_empty_taxonomy_selections_are_skipped(): void {
		$tax_query = $this->build(
			array(
				'feature' => array(),
				'size'    => array( 56 ),
			),
			array( 'feature' => 'AND' )
		);

		$this->assertNull( $this->clause_for( $tax_query, 'feature' ) );
		$this->assertNotNull( $this->clause_for( $tax_query, 'size' ) );
	}

	public function test_term_ids_are_cast_deduplicated_and_emptied_out(): void {
		$clause = $this->clause_for(
			$this->build( array( 'feature' => array( '12', 12, 0, '', 34 ) ) ),
			'feature'
		);

		$this->assertSame( array( 12, 34 ), $clause['terms'] );
	}

	/**
	 * Relations are coerced to AND or OR, never left as given.
	 *
	 * @dataProvider provide_logic_values
	 *
	 * @param mixed  $input    Raw relation.
	 * @param string $expected Normalised relation.
	 */
	public function test_logic_normalisation( $input, string $expected ): void {
		$this->assertSame( $expected, Kate_Toms_House_Tax_Query::normalise_logic( $input ) );
	}

	/**
	 * Relations as they might arrive from block markup or a query string.
	 *
	 * @return array[]
	 */
	public static function provide_logic_values(): array {
		return array(
			'exact and'     => array( 'AND', 'AND' ),
			'lowercase and' => array( 'and', 'AND' ),
			'padded and'    => array( ' And ', 'AND' ),
			'exact or'      => array( 'OR', 'OR' ),
			'unrecognised'  => array( 'EXCLUDE', 'OR' ),
			'empty string'  => array( '', 'OR' ),
			'null'          => array( null, 'OR' ),
			'not a string'  => array( array( 'AND' ), 'OR' ),
		);
	}
}
