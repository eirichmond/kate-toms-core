<?php
/**
 * Blueprint SEO metadata.
 *
 * Writes the Yoast SEO title, meta description, canonical, robots and sitemap
 * settings for the pages a Blueprint creates.
 *
 * @package    Kate_Toms_Core
 * @subpackage Kate_Toms_Core/includes
 */

declare(strict_types=1);

/**
 * Blueprint SEO metadata.
 *
 * Meta titles reuse the page title convention, so a page's browser title and
 * its post title stay in step. Meta descriptions follow the fixed sentences
 * agreed in the Blueprint brief.
 *
 * The parent page's meta description is deliberately left blank — the content
 * team writes it by hand once the house copy exists.
 */
class Kate_Toms_Blueprint_SEO {

	/**
	 * Meta description templates, keyed by page key.
	 *
	 * `%s` is replaced with the house display title. Pages absent from this
	 * map (the parent) get no description.
	 *
	 * @var array<string, string>
	 */
	private const DESCRIPTIONS = array(
		'availability' => 'View live availability and prices for %s',
		'book'         => 'Book %s today',
		'gallery'      => 'Explore photos of %s. View the bedrooms, living spaces, garden and stylish interiors',
		'facts'        => 'View the key facts for %s, including bedrooms, facilities, parking, dog-friendly policies, accessibility and booking information',
		'more'         => 'Discover the best things to do near %s',
	);

	/**
	 * Page keys that must not be indexed or listed in the sitemap, and whose
	 * canonical URL points at the parent house page.
	 *
	 * @var string[]
	 */
	private const CANONICAL_TO_PARENT = array( 'availability', 'book' );

	/**
	 * Applies all Blueprint SEO metadata to a created page.
	 *
	 * @param int    $post_id       Page post ID.
	 * @param string $key           Page key.
	 * @param string $display_title House display title.
	 * @param string $parent_url    Permalink of the parent house page.
	 *
	 * @return void
	 */
	public function apply( int $post_id, string $key, string $display_title, string $parent_url ): void {
		// Every page's meta title mirrors its post title, the parent included —
		// so the parent starts as the house name rather than empty. Only its
		// meta description is left blank for the content team to write.
		update_post_meta( $post_id, '_yoast_wpseo_title', Kate_Toms_Blueprint_Templates::build_title( $display_title, $key ) );

		if ( isset( self::DESCRIPTIONS[ $key ] ) ) {
			update_post_meta(
				$post_id,
				'_yoast_wpseo_metadesc',
				sprintf( self::DESCRIPTIONS[ $key ], $display_title )
			);
		}

		if ( in_array( $key, self::CANONICAL_TO_PARENT, true ) ) {
			update_post_meta( $post_id, '_yoast_wpseo_canonical', $parent_url );
			update_post_meta( $post_id, '_yoast_wpseo_meta-robots-noindex', '1' );
			update_post_meta( $post_id, '_yoast_wpseo_sitemap-include', 'never' );
		}
	}

	/**
	 * Builds the public permalink for a house page from its slug.
	 *
	 * `get_permalink()` returns an unpretty `?p=` URL while a post is still a
	 * draft, so the canonical is composed from the post type's rewrite slug
	 * instead. Blueprint pages are always created as drafts.
	 *
	 * @param string $house_slug Parent house post slug.
	 *
	 * @return string Absolute parent page URL with a trailing slash.
	 */
	public static function build_parent_url( string $house_slug ): string {
		$post_type = get_post_type_object( 'houses' );
		$base      = 'houses';

		if ( $post_type instanceof WP_Post_Type && is_array( $post_type->rewrite ) && ! empty( $post_type->rewrite['slug'] ) ) {
			$base = (string) $post_type->rewrite['slug'];
		}

		return home_url( user_trailingslashit( $base . '/' . $house_slug ) );
	}
}
