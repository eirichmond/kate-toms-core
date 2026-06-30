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
 * helper centralises the parts the taxonomy term-sections block needs: the
 * region location term ID, its heading, and its section background colour.
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
	 * }
	 */
	function kate_toms_core_get_region_sections() {
		$sections = array(
			array(
				'term_id'    => 604,
				'key'        => 'cotswolds',
				'heading'    => "kate & tom's in the Cotswolds",
				'background' => 'sectioncotswolds',
			),
			array(
				'term_id'    => 810,
				'key'        => 'coast',
				'heading'    => "kate & tom's by the Coast",
				'background' => 'sectioncoast',
			),
			array(
				'term_id'    => 790,
				'key'        => 'country',
				'heading'    => "kate & tom's in the Country",
				'background' => 'sectioncountry',
			),
			array(
				'term_id'    => 603,
				'key'        => 'town',
				'heading'    => "kate & tom's in Town",
				// Town deliberately reuses the coast section colour, matching the
				// existing archive-houses.html / taxonomy.html markup.
				'background' => 'sectioncoast',
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
