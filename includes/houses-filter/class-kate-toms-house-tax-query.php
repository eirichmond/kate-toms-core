<?php
/**
 * Taxonomy query builder for landing page house sections.
 *
 * @package Kate_Toms_Core
 */

if ( ! class_exists( 'Kate_Toms_House_Tax_Query' ) ) {

	/**
	 * Builds the whole tax_query for a landing page section.
	 *
	 * Different taxonomies are always ANDed together: a house must match the
	 * location and the features and the size. What varies is the relation
	 * *within* one taxonomy when several of its terms are selected. Until now
	 * that was always OR, so "Hen Party Houses" + "Party House with a Hot Tub"
	 * pulled in every house carrying either tag — including houses that do not
	 * take hen parties. Each taxonomy can now be set to AND instead, so a house
	 * has to carry every selected term.
	 *
	 * `location` is deliberately not configurable. Its clauses come from
	 * Kate_Toms_Location_Tax_Query, which already splits the selection into a
	 * broad region and the granular locations beneath it — those granular
	 * locations are alternatives, and ANDing them would ask for one house to be
	 * in several places at once.
	 *
	 * Pure logic — no WordPress dependency, so it can be unit tested directly.
	 */
	class Kate_Toms_House_Tax_Query {

		/**
		 * Taxonomies whose within-taxonomy relation can be chosen per section.
		 *
		 * Order fixes the order of the emitted clauses.
		 *
		 * @var string[]
		 */
		const CONFIGURABLE_TAXONOMIES = array( 'feature', 'size', 'type', 'occasion' );

		/**
		 * Relation used when a section does not specify one.
		 *
		 * OR reproduces the behaviour every section had before the relation was
		 * configurable, so existing pages are unaffected by this change.
		 *
		 * @var string
		 */
		const DEFAULT_LOGIC = 'OR';

		/**
		 * Build the full tax_query for a section.
		 *
		 * @param array $term_ids   Selected term IDs keyed by taxonomy, e.g.
		 *                          array( 'location' => array( 810 ), 'feature' => array( 12, 34 ) ).
		 * @param array $logic      Within-taxonomy relation keyed by taxonomy,
		 *                          each 'AND' or 'OR'. Missing or unrecognised
		 *                          entries fall back to OR.
		 * @param int[] $region_ids Term IDs that count as broad regions.
		 * @return array A tax_query, including its 'relation' when more than one
		 *               clause is present. Empty when nothing is selected.
		 */
		public static function build( array $term_ids, array $logic, array $region_ids ) {
			$tax_query = array();

			$locations = isset( $term_ids['location'] ) ? (array) $term_ids['location'] : array();

			if ( ! empty( $locations ) ) {
				$tax_query = array_merge(
					$tax_query,
					Kate_Toms_Location_Tax_Query::build( $locations, $region_ids )
				);
			}

			foreach ( self::CONFIGURABLE_TAXONOMIES as $taxonomy ) {
				$terms = isset( $term_ids[ $taxonomy ] ) ? (array) $term_ids[ $taxonomy ] : array();
				$terms = array_values( array_unique( array_filter( array_map( 'intval', $terms ) ) ) );

				if ( empty( $terms ) ) {
					continue;
				}

				$tax_query[] = array(
					'taxonomy' => $taxonomy,
					'field'    => 'term_id',
					'terms'    => $terms,
					// WP_Query spells "match every term" as the AND operator and
					// "match any term" as IN.
					'operator' => 'AND' === self::normalise_logic( $logic[ $taxonomy ] ?? null ) ? 'AND' : 'IN',
				);
			}

			if ( count( $tax_query ) > 1 ) {
				$tax_query['relation'] = 'AND';
			}

			return $tax_query;
		}

		/**
		 * Coerce a stored relation to 'AND' or 'OR'.
		 *
		 * Anything unrecognised — null, an empty string, a value hand-edited in
		 * the block markup — becomes the default rather than an error, so a bad
		 * value degrades to the pre-existing behaviour instead of emptying the
		 * section.
		 *
		 * @param mixed $logic Raw relation.
		 * @return string 'AND' or 'OR'.
		 */
		public static function normalise_logic( $logic ) {
			if ( ! is_string( $logic ) ) {
				return self::DEFAULT_LOGIC;
			}

			return 'AND' === strtoupper( trim( $logic ) ) ? 'AND' : self::DEFAULT_LOGIC;
		}
	}
}
