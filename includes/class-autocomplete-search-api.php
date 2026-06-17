<?php
/**
 * Autocomplete Search API Class
 *
 * @package Kate_Toms_Core
 */

/**
 * Class Autocomplete_Search_API
 */
class Autocomplete_Search_API {

	/**
	 * Page IDs for landing pages used as data sources.
	 */
	const LOCATIONS_PAGE_ID = 27142;
	const FEATURES_PAGE_ID  = 48958;

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
		add_action( 'save_post', array( $this, 'invalidate_cache' ) );
	}

	/**
	 * Register the REST API routes.
	 */
	public function register_routes() {
		register_rest_route(
			'kate-toms/v1',
			'/autocomplete-search',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_search_items' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Get search items for autocomplete including houses, locations, and features.
	 *
	 * Houses come from wp_posts. Locations and Features are parsed from
	 * their respective landing page block content.
	 *
	 * @return WP_REST_Response The response object containing search items.
	 */
	public function get_search_items() {
		$search_items = array();
		$blog_id      = get_current_blog_id();

		// Get all houses from wp_posts.
		$houses_query = new WP_Query(
			array(
				'post_type'      => 'houses',
				'posts_per_page' => -1,
				'post_status'    => 'publish',
				'meta_key'       => 'sleeps_max',
				'orderby'        => 'meta_value_num',
				'order'          => ( $blog_id == 7 || $blog_id == 13 ) ? 'ASC' : 'DESC',
			)
		);

		if ( $houses_query->have_posts() ) {
			while ( $houses_query->have_posts() ) {
				$houses_query->the_post();
				$post_id = get_the_ID();

				$search_items[] = array(
					'category' => 'Houses',
					'url'      => $this->normalize_url( get_permalink( $post_id ) ),
					'thumb'    => $this->normalize_url( get_the_post_thumbnail_url( $post_id, 'thumbnail' ) ),
					'label'    => get_the_title(),
					'desc'     => get_post_meta( $post_id, 'brief_description', true ),
					'house_id' => $post_id,
					'blog_id'  => $blog_id,
					'post_id'  => $post_id,
				);
			}
			wp_reset_postdata();
		}

		// Get locations from landing page block content.
		$search_items = array_merge(
			$search_items,
			$this->parse_landing_page_items( self::LOCATIONS_PAGE_ID, 'Locations' )
		);

		// Get features from landing page block content.
		$search_items = array_merge(
			$search_items,
			$this->parse_landing_page_items( self::FEATURES_PAGE_ID, 'Features' )
		);

		// Cap result descriptions for the autocomplete dropdown display.
		foreach ( $search_items as &$item ) {
			if ( isset( $item['desc'] ) ) {
				$item['desc'] = $this->truncate_desc( $item['desc'] );
			}
		}
		unset( $item );

		return rest_ensure_response( $search_items );
	}

	/**
	 * Truncate a result description to a maximum character length.
	 *
	 * Length is inclusive of the appended ellipsis, so the returned string
	 * never exceeds $length characters.
	 *
	 * @param string $desc   The description text.
	 * @param int    $length Maximum length, including the ellipsis. Default 50.
	 * @return string The truncated description.
	 */
	private function truncate_desc( $desc, $length = 50 ) {
		$desc = trim( (string) $desc );

		if ( mb_strlen( $desc ) <= $length ) {
			return $desc;
		}

		return rtrim( mb_substr( $desc, 0, $length - 1 ) ) . '…';
	}

	/**
	 * Parse a landing page's block content to extract search items.
	 *
	 * Expects a page built with the "Image Set Fill" pattern where each item
	 * is a core/group block with an `href` attribute containing nested
	 * core/image, core/heading, and optionally core/paragraph blocks.
	 *
	 * @param int    $page_id  The landing page post ID.
	 * @param string $category The category label for search results.
	 * @return array Array of search item arrays.
	 */
	private function parse_landing_page_items( $page_id, $category ) {
		$transient_key = 'autocomplete_search_' . $page_id;
		$cached        = get_transient( $transient_key );

		if ( false !== $cached ) {
			return $cached;
		}

		$content = get_post_field( 'post_content', $page_id );

		if ( empty( $content ) ) {
			return array();
		}

		$blocks = parse_blocks( $content );
		$items  = array();
		$blog_id = get_current_blog_id();

		$this->find_linked_groups( $blocks, $items, $category, $blog_id );

		set_transient( $transient_key, $items, DAY_IN_SECONDS );

		return $items;
	}

	/**
	 * Recursively find core/group blocks with an href attribute and extract search item data.
	 *
	 * @param array  $blocks   Array of parsed blocks.
	 * @param array  $items    Reference to the items array to populate.
	 * @param string $category The category label.
	 * @param int    $blog_id  The current blog ID.
	 */
	private function find_linked_groups( $blocks, &$items, $category, $blog_id ) {
		foreach ( $blocks as $block ) {
			// A group block with href is a search item.
			if ( 'core/group' === $block['blockName'] && ! empty( $block['attrs']['href'] ) ) {
				$item = $this->extract_item_from_group( $block, $category, $blog_id );
				if ( $item ) {
					$items[] = $item;
				}
				continue;
			}

			// Recurse into inner blocks.
			if ( ! empty( $block['innerBlocks'] ) ) {
				$this->find_linked_groups( $block['innerBlocks'], $items, $category, $blog_id );
			}
		}
	}

	/**
	 * Extract search item data from a linked group block.
	 *
	 * @param array  $block    The parsed group block.
	 * @param string $category The category label.
	 * @param int    $blog_id  The current blog ID.
	 * @return array|null Search item array or null if no label found.
	 */
	private function extract_item_from_group( $block, $category, $blog_id ) {
		$url   = $block['attrs']['href'];
		$thumb = '';
		$label = '';
		$desc  = '';

		$this->extract_inner_block_data( $block['innerBlocks'], $thumb, $label, $desc );

		if ( empty( $label ) ) {
			return null;
		}

		return array(
			'category' => $category,
			'url'      => $this->normalize_url( $url ),
			'thumb'    => $this->normalize_url( $thumb ),
			'label'    => $label,
			'desc'     => $desc,
			'house_id' => null,
			'blog_id'  => $blog_id,
			'post_id'  => null,
		);
	}

	/**
	 * Recursively extract image src, heading text, and paragraph text from inner blocks.
	 *
	 * @param array  $blocks Array of parsed inner blocks.
	 * @param string $thumb  Reference to the thumbnail URL.
	 * @param string $label  Reference to the label text.
	 * @param string $desc   Reference to the description text.
	 */
	private function extract_inner_block_data( $blocks, &$thumb, &$label, &$desc ) {
		foreach ( $blocks as $inner ) {
			if ( 'core/image' === $inner['blockName'] && empty( $thumb ) ) {
				// Extract src from the image block's innerHTML.
				if ( preg_match( '/src="([^"]+)"/', $inner['innerHTML'], $matches ) ) {
					$thumb = $matches[1];
				}
			} elseif ( 'core/heading' === $inner['blockName'] && empty( $label ) ) {
				$label = html_entity_decode( trim( wp_strip_all_tags( $inner['innerHTML'] ) ), ENT_QUOTES, 'UTF-8' );
			} elseif ( 'core/paragraph' === $inner['blockName'] && empty( $desc ) ) {
				$text = html_entity_decode( trim( wp_strip_all_tags( $inner['innerHTML'] ) ), ENT_QUOTES, 'UTF-8' );
				if ( ! empty( $text ) ) {
					$desc = $text;
				}
			}

			// Recurse into nested blocks (e.g. inner groups containing heading/paragraph).
			if ( ! empty( $inner['innerBlocks'] ) ) {
				$this->extract_inner_block_data( $inner['innerBlocks'], $thumb, $label, $desc );
			}
		}
	}

	/**
	 * Invalidate landing page transient caches when those pages are saved.
	 *
	 * @param int $post_id The post ID being saved.
	 */
	public function invalidate_cache( $post_id ) {
		if ( self::LOCATIONS_PAGE_ID === $post_id ) {
			delete_transient( 'autocomplete_search_' . self::LOCATIONS_PAGE_ID );
		}

		if ( self::FEATURES_PAGE_ID === $post_id ) {
			delete_transient( 'autocomplete_search_' . self::FEATURES_PAGE_ID );
		}
	}

	/**
	 * Normalize URLs for environment-specific deployment.
	 * Only replace URLs when deploying to production.
	 * 
	 * @param string $url The URL to normalize.
	 * @return string The normalized URL.
	 */
	private function normalize_url($url) {
		if (empty($url)) {
			return '';
		}
		
		// Only replace .test with .com if we're preparing for production deployment
		// For local development, keep the local URLs
		$site_url = get_site_url();
		if (strpos($site_url, '.test') !== false) {
			// We're in local environment, keep local URLs
			return $url;
		}
		
		// We're in production environment, replace .test with .com if needed
		return str_replace('.test', '.com', $url);
	}
}