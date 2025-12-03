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
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
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
	 * Modern implementation using wp_posts table and taxonomies.
	 * 
	 * @return WP_REST_Response The response object containing search items
	 */
	public function get_search_items() {
		$search_items = array();
		$blog_id = get_current_blog_id();

		// Get all houses from wp_posts
		$houses_query = new WP_Query(array(
			'post_type'      => 'houses',
			'posts_per_page' => -1,
			'post_status'    => 'publish',
			'meta_key'       => 'sleeps_max',
			'orderby'        => 'meta_value_num',
			'order'          => ($blog_id == 7 || $blog_id == 13) ? 'ASC' : 'DESC',
		));

		if ($houses_query->have_posts()) {
			while ($houses_query->have_posts()) {
				$houses_query->the_post();
				$post_id = get_the_ID();
				
				$search_items[] = array(
					'category'  => 'Houses',
					'url'       => $this->normalize_url(get_permalink($post_id)),
					'thumb'     => $this->normalize_url(get_the_post_thumbnail_url($post_id, 'thumbnail')),
					'label'     => get_the_title(),
					'desc'      => get_post_meta($post_id, 'brief_description', true),
					'house_id'  => $post_id,
					'blog_id'   => $blog_id,
					'post_id'   => $post_id,
				);
			}
			wp_reset_postdata();
		}

		// Get location taxonomy terms
		$location_terms = get_terms(array(
			'taxonomy'   => 'location',
			'hide_empty' => false,
		));

		if (!is_wp_error($location_terms) && !empty($location_terms)) {
			foreach ($location_terms as $term) {
				// Skip if empty description
				if (empty($term->description)) {
					continue;
				}

				// Get term image and custom URL if available
				$term_image = get_term_meta($term->term_id, 'image', true);
				$custom_url = get_term_meta($term->term_id, 'custom_url', true);

				$search_items[] = array(
					'category'  => 'Locations',
					'url'       => $this->normalize_url($custom_url ?: get_term_link($term)),
					'thumb'     => $this->normalize_url($term_image ? wp_get_attachment_url($term_image) : ''),
					'label'     => $term->name,
					'desc'      => wp_trim_words($term->description, 10),
					'house_id'  => null,
					'blog_id'   => $blog_id,
					'post_id'   => null,
					'term_id'   => $term->term_id,
				);
			}
		}

		// Get feature taxonomy terms
		$feature_terms = get_terms(array(
			'taxonomy'   => 'feature',
			'hide_empty' => false,
		));

		if (!is_wp_error($feature_terms) && !empty($feature_terms)) {
			foreach ($feature_terms as $term) {
				// Skip if empty description
				if (empty($term->description)) {
					continue;
				}

				// Get term image and custom URL if available
				$term_image = get_term_meta($term->term_id, 'image', true);
				$custom_url = get_term_meta($term->term_id, 'custom_url', true);

				$search_items[] = array(
					'category'  => 'Features',
					'url'       => $this->normalize_url($custom_url ?: get_term_link($term)),
					'thumb'     => $this->normalize_url($term_image ? wp_get_attachment_url($term_image) : ''),
					'label'     => $term->name,
					'desc'      => wp_trim_words($term->description, 10),
					'house_id'  => null,
					'blog_id'   => $blog_id,
					'post_id'   => null,
					'term_id'   => $term->term_id,
				);
			}
		}

		return rest_ensure_response($search_items);
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