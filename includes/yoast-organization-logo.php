<?php
/**
 * Repair the logo on Yoast's Organization schema node.
 *
 * Yoast caches the dimensions and URL of the Organization logo in the
 * `company_logo_meta` option rather than reading the attachment on each
 * request. On this site that option holds an array of nine empty strings, so
 * the Organization node goes out with `"url": null` and `"contentUrl": null`
 * where an absolute logo URL belongs.
 *
 * Yoast cannot self-heal from this: `Image_Helper::get_attachment_meta_from_settings()`
 * only rebuilds when the stored meta is falsy, and an array of empty strings
 * is truthy in PHP, so the blank record is taken at face value indefinitely.
 * Re-saving the logo in Yoast's settings would repair it per environment;
 * rebuilding here fixes local, staging and production from one deployment
 * and survives the option being blanked again.
 *
 * The URL is resolved from the attachment, so it carries whichever domain the
 * environment serves rather than a hardcoded one.
 *
 * @package Kate_Toms_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_filter( 'wpseo_schema_company_logo_meta', 'kate_toms_core_repair_company_logo_meta' );

/**
 * Rebuild Yoast's company logo meta when it carries no usable URL.
 *
 * @param mixed $meta The logo meta Yoast resolved, normally an array.
 * @return mixed The original meta, or a rebuilt one.
 */
function kate_toms_core_repair_company_logo_meta( $meta ) {
	if ( is_array( $meta ) && ! empty( $meta['url'] ) ) {
		return $meta;
	}

	if ( ! class_exists( 'WPSEO_Options' ) || ! class_exists( 'WPSEO_Image_Utils' ) ) {
		return $meta;
	}

	$logo_id = (int) WPSEO_Options::get( 'company_logo_id', 0 );

	if ( ! $logo_id ) {
		return $meta;
	}

	$variations = WPSEO_Image_Utils::filter_usable_file_size( WPSEO_Image_Utils::get_variations( $logo_id ) );

	if ( empty( $variations ) ) {
		return $meta;
	}

	// Variations come back best-first, matching how Yoast picks one itself.
	$best = reset( $variations );

	return ( is_array( $best ) && ! empty( $best['url'] ) ) ? $best : $meta;
}
