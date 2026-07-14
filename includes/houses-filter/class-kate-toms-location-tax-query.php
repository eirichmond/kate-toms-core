<?php
/**
 * Location tax_query builder for landing page sections.
 *
 * @package Kate_Toms_Core
 */

if ( ! class_exists( 'Kate_Toms_Location_Tax_Query' ) ) {

	/**
	 * Builds the `location` clauses of a landing page section's tax_query.
	 *
	 * Pure logic — no WordPress dependency, so it can be unit tested directly.
	 */
	class Kate_Toms_Location_Tax_Query {

		/**
		 * Build the `location` tax_query clauses for a landing page section.
		 *
		 * A section's locationTermIds combine a broad region (Cotswolds / Coast /
		 * Country / Town) with the granular locations the migration injects from
		 * `ah_from_locations` — e.g. the /party-houses/wales/ Coast section is
		 * Coast + [Conwy, Newquay, Wales].
		 *
		 * The granular locations are alternatives to one another, so a house
		 * qualifies when it is in the region AND in any one of them. ANDing the
		 * whole list into a single clause instead asks for one house to be in
		 * several places at once, which can never match.
		 *
		 * @param int[] $location_term_ids Selected `location` term IDs.
		 * @param int[] $region_ids        Term IDs that count as broad regions.
		 * @return array[] Between zero and two tax_query clauses, to be merged
		 *                 into the section's tax_query with an AND relation.
		 */
		public static function build( array $location_term_ids, array $region_ids ) {
			$location_term_ids = array_values(
				array_unique( array_filter( array_map( 'intval', $location_term_ids ) ) )
			);

			if ( empty( $location_term_ids ) ) {
				return array();
			}

			$region_ids = array_map( 'intval', $region_ids );

			$regions  = array_values( array_intersect( $location_term_ids, $region_ids ) );
			$granular = array_values( array_diff( $location_term_ids, $region_ids ) );

			$clauses = array();

			foreach ( array( $regions, $granular ) as $terms ) {
				if ( empty( $terms ) ) {
					continue;
				}

				$clauses[] = array(
					'taxonomy' => 'location',
					'field'    => 'term_id',
					'terms'    => $terms,
					'operator' => 'IN',
				);
			}

			return $clauses;
		}
	}
}
