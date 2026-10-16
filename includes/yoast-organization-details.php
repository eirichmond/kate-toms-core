<?php
/**
 * Business details on Yoast's Organization schema node.
 *
 * Adds the postal address, the brand's spelling variants, an international
 * telephone number and a reservations ContactPoint to the sitewide
 * `#organization` node, per TAL's Organization schema brief (BugHerd #500).
 *
 * These are merged into the node Yoast already builds rather than printed as
 * a separate Organization: the house and Trustpilot schema both hang off that
 * one `@id`, and a second Organization would compete with it. Keys Yoast
 * already sets (logo, sameAs, email) are left alone; `telephone` is replaced
 * because Yoast Premium emits the local `01242` form from its Organization
 * settings and the brief asks for the international one. Premium adds that on
 * this same filter at the default priority, hence priority 11 here. Any
 * alternate name entered in Yoast's settings is kept alongside the variants
 * below.
 *
 * @package Kate_Toms_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_filter( 'wpseo_schema_organization', 'kate_toms_core_add_organization_details', 11 );

/**
 * Merge the business details into the Organization node.
 *
 * @param mixed $data The Organization node Yoast built, normally an array.
 * @return mixed The node with the details merged in.
 */
function kate_toms_core_add_organization_details( $data ) {
	if ( ! is_array( $data ) ) {
		return $data;
	}

	$telephone = '+44 1242 235151';

	$alternate_names = array(
		'Kate and Tom\'s',
		'Kate & Tom\'s',
		'Kate and Toms',
		'Kate & Toms',
		'kate and toms',
		'kate & toms',
	);

	if ( ! empty( $data['alternateName'] ) ) {
		$alternate_names = array_merge( (array) $data['alternateName'], $alternate_names );
	}

	$data['alternateName'] = array_values( array_unique( $alternate_names ) );

	$data['address'] = array(
		'@type'           => 'PostalAddress',
		'streetAddress'   => 'Top Floor, Royal Mews, St George\'s Place',
		'addressLocality' => 'Cheltenham',
		'postalCode'      => 'GL50 3PQ',
		'addressCountry'  => 'GB',
	);

	$data['telephone'] = $telephone;

	$data['contactPoint'] = array(
		'@type'             => 'ContactPoint',
		'contactType'       => 'reservations',
		'telephone'         => $telephone,
		'email'             => 'hello@kateandtoms.com',
		'areaServed'        => 'GB',
		'availableLanguage' => 'en',
	);

	return $data;
}
