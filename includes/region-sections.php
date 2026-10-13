<?php
/**
 * Region section configuration.
 *
 * Single source of truth for the four location "region" sections used across
 * the houses archive and taxonomy templates (Cotswolds, Coast, Country, Town).
 *
 * The region term IDs were previously hard-coded and duplicated across the
 * theme templates (archive-houses.html / taxonomy.html), the
 * houses-filtered-results block render, and the Houses Filter REST API. This
 * helper centralises the region location term ID, its heading, its section
 * background colour and the title bar colour of its house cards.
 *
 * @package Kate_Toms_Core
 */

if ( ! function_exists( 'kate_toms_core_get_region_sections' ) ) {
	/**
	 * Get the ordered list of region sections.
	 *
	 * Each section maps a top-level `location` taxonomy term to the presentation
	 * used when grouping houses on the archive / taxonomy templates.
	 *
	 * @return array[] {
	 *     List of region sections, in display order.
	 *
	 *     @type int    $term_id    The `location` taxonomy term ID for the region.
	 *     @type string $key        Internal key (cotswolds|coast|country|town).
	 *     @type string $heading    Section heading text.
	 *     @type string $background  Group background colour slug (theme palette).
	 *     @type string $card_title Card title bar colour slug (theme palette).
	 * }
	 */
	function kate_toms_core_get_region_sections() {
		$sections = array(
			array(
				'term_id'    => 604,
				'key'        => 'cotswolds',
				'heading'    => "kate & tom's in the Cotswolds",
				'background' => 'sectioncotswolds',
				'card_title' => 'colorfive', // Yellow.
			),
			array(
				'term_id'    => 810,
				'key'        => 'coast',
				'heading'    => "kate & tom's by the Coast",
				'background' => 'sectioncoast',
				'card_title' => 'coloreight', // Blue.
			),
			array(
				'term_id'    => 790,
				'key'        => 'country',
				'heading'    => "kate & tom's in the Country",
				'background' => 'sectioncountry',
				'card_title' => 'titlecolorthree', // Green.
			),
			array(
				'term_id'    => 603,
				'key'        => 'town',
				'heading'    => "kate & tom's in Town",
				// Town deliberately reuses the coast section colour, matching the
				// existing archive-houses.html / taxonomy.html markup.
				'background' => 'sectioncoast',
				'card_title' => 'coloreight', // Blue.
			),
		);

		/**
		 * Filter the region sections used by the houses archive / taxonomy templates.
		 *
		 * @param array[] $sections The region section definitions.
		 */
		return apply_filters( 'kate_toms_core_region_sections', $sections );
	}
}

if ( ! function_exists( 'kate_toms_core_get_region_term_ids' ) ) {
	/**
	 * Get the `location` term IDs of the four broad regions.
	 *
	 * Used to tell a section's broad region apart from the granular locations
	 * the migration injects alongside it.
	 *
	 * @return int[] Region location term IDs.
	 */
	function kate_toms_core_get_region_term_ids() {
		return array_map(
			'intval',
			wp_list_pluck( kate_toms_core_get_region_sections(), 'term_id' )
		);
	}
}

if ( ! function_exists( 'kate_toms_core_get_region_section_for_terms' ) ) {
	/**
	 * Find the region section for a list of `location` term IDs.
	 *
	 * Sections often pair a region with a granular location (e.g. Lancashire +
	 * By the Coast), in either order, so every ID is checked rather than only the
	 * first. A child of a region term counts as that region. The first ID that
	 * resolves to a region wins.
	 *
	 * @param int[] $term_ids Location term IDs, in the order they were selected.
	 * @return array|null The matching region section, or null when none matches.
	 */
	function kate_toms_core_get_region_section_for_terms( $term_ids ) {
		$sections = array();
		foreach ( kate_toms_core_get_region_sections() as $section ) {
			$sections[ (int) $section['term_id'] ] = $section;
		}

		foreach ( array_map( 'intval', (array) $term_ids ) as $term_id ) {
			if ( isset( $sections[ $term_id ] ) ) {
				return $sections[ $term_id ];
			}

			foreach ( get_ancestors( $term_id, 'location', 'taxonomy' ) as $ancestor_id ) {
				if ( isset( $sections[ $ancestor_id ] ) ) {
					return $sections[ $ancestor_id ];
				}
			}
		}

		return null;
	}
}

if ( ! function_exists( 'kate_toms_core_get_region_section_by_key' ) ) {
	/**
	 * Find a region section by its key (cotswolds|coast|country|town).
	 *
	 * @param string $key Region key.
	 * @return array|null The matching region section, or null when none matches.
	 */
	function kate_toms_core_get_region_section_by_key( $key ) {
		foreach ( kate_toms_core_get_region_sections() as $section ) {
			if ( $section['key'] === $key ) {
				return $section;
			}
		}

		return null;
	}
}
