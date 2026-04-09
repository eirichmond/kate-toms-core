<?php
/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              https://elliottrichmond.co.uk
 * @since             1.0.0
 * @package           Kate_Toms_Core
 *
 * @wordpress-plugin
 * Plugin Name:       Kate and Toms Core
 * Plugin URI:        https://kateandtoms.com
 * Description:       Main plugin file for kate&toms functionality
 * Version:           1.0.0
 * Author:            Elliott Richmond
 * Author URI:        https://elliottrichmond.co.uk/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       kate-toms-core
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Currently plugin version.
 * Start at version 1.0.0 and use SemVer - https://semver.org
 * Rename this for your plugin and update it as you release new versions.
 */
define( 'KATE_TOMS_CORE_VERSION', '1.0.0' );

/**
 * Absolute path to this plugin's main file.
 *
 * Used as a stable reference for plugins_url() / plugin_dir_path() /
 * plugin_dir_url() calls made from other files inside the plugin, so those
 * callers don't have to know their own depth relative to the plugin root.
 */
define( 'KATE_TOMS_CORE_PLUGIN_FILE', __FILE__ );

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-kate-toms-core-activator.php
 */
function activate_kate_toms_core() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-kate-toms-core-activator.php';
	Kate_Toms_Core_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-kate-toms-core-deactivator.php
 */
function deactivate_kate_toms_core() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-kate-toms-core-deactivator.php';
	Kate_Toms_Core_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_kate_toms_core' );
register_deactivation_hook( __FILE__, 'deactivate_kate_toms_core' );

// Load WP CLI cache warmer commands (only in CLI context).
if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-calendar-cache-warmer.php';
}

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path( __FILE__ ) . 'includes/class-kate-toms-core.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function run_kate_toms_core() {

	$plugin = new Kate_Toms_Core();
	$plugin->run();
}
run_kate_toms_core();


// below are helper function that run globally

// Removed - was forcing all thumbnails to 'square' size
// function kate_toms_post_thumbnail_size_filter( $size, $post_id ) {
// 	$size = 'square';
// 	return $size;
// }
// add_filter( 'post_thumbnail_size', 'kate_toms_post_thumbnail_size_filter', 10, 2 );


/**
 * Filter post thumbnail HTML to replace all image domains with kateandtoms.com in local environment.
 *
 * @param string       $html              The post thumbnail HTML.
 * @param int          $post_id           The post ID.
 * @param int          $post_thumbnail_id The post thumbnail ID.
 * @param string|int[] $size              The size of the image.
 * @param array        $attr              Attributes for the image.
 * @return string       Modified HTML with domains replaced if local.
 */
function kate_toms_post_thumbnail_html_filter( $html, $post_id, $post_thumbnail_id, $size, $attr ) {
	if ( wp_get_environment_type() === 'local' || wp_get_environment_type() === 'staging' ) {
		$callback = function( $matches ) {
			$url = $matches[0];
			$parsed_url = wp_parse_url( $url );
			if ( $parsed_url && isset( $parsed_url['host'] ) ) {
				$parsed_url['host'] = 'kateandtoms.com';
				$new_url = ( isset( $parsed_url['scheme'] ) ? $parsed_url['scheme'] . '://' : '' ) .
					$parsed_url['host'] .
					( isset( $parsed_url['port'] ) ? ':' . $parsed_url['port'] : '' ) .
					( isset( $parsed_url['path'] ) ? $parsed_url['path'] : '' ) .
					( isset( $parsed_url['query'] ) ? '?' . $parsed_url['query'] : '' ) .
					( isset( $parsed_url['fragment'] ) ? '#' . $parsed_url['fragment'] : '' );
				return $new_url;
			}
			return $url;
		};
		// Replace all URLs in the HTML.
		$filtered_html = preg_replace_callback( '#https?://[\w\.-]+(?:/[^"\s]*)?#', $callback, $html );
		return $filtered_html;
	}
	return $html;
}
add_filter( 'post_thumbnail_html', 'kate_toms_post_thumbnail_html_filter', 10, 5 );

/**
 * Filter the image src array to always return 'kateandtoms.com' as the URL.
 *
 * @param array        $image         Array of image data: [0] => URL, [1] => width, [2] => height, [3] => is_icon.
 * @param int          $attachment_id Attachment ID.
 * @param string|int[] $size          Size of image.
 * @param bool         $icon          Whether the image is an icon.
 * @return array       Modified image array with URL replaced.
 */
function kate_toms_replace_image_srcset_url( $image, $attachment_id, $size, $icon ) {
	if ( wp_get_environment_type() === 'local' || wp_get_environment_type() === 'staging' ) {
		if ( is_array( $image ) && isset( $image[0] ) ) {
			$parsed_url = wp_parse_url( $image[0] );
			if ( $parsed_url && isset( $parsed_url['host'] ) ) {
				$parsed_url['host'] = 'kateandtoms.com';
				// Rebuild the URL
				$image[0] = ( isset( $parsed_url['scheme'] ) ? $parsed_url['scheme'] . '://' : '' ) .
					$parsed_url['host'] .
					( isset( $parsed_url['port'] ) ? ':' . $parsed_url['port'] : '' ) .
					( isset( $parsed_url['path'] ) ? $parsed_url['path'] : '' ) .
					( isset( $parsed_url['query'] ) ? '?' . $parsed_url['query'] : '' ) .
					( isset( $parsed_url['fragment'] ) ? '#' . $parsed_url['fragment'] : '' );
			}
		}
	}
	return $image;
}
add_filter( 'wp_get_attachment_image_src', 'kate_toms_replace_image_srcset_url', 10, 4 );

function kate_toms_calculate_image_srcset ( $sources, $size_array, $image_src, $image_meta, $attachment_id ) {
	if ( wp_get_environment_type() === 'local' || wp_get_environment_type() === 'staging' ) {
		$cdn_url = 'https://kateandtoms.com'; // Replace with your actual CDN URL
		$site_url = wp_parse_url( site_url(), PHP_URL_HOST );

		foreach ( $sources as $width => &$source ) {
			$source_url  = $source['url'];
			$source_host = parse_url( $source_url, PHP_URL_HOST );
			// Only replace if the URL is from our site
			if ( $source_host === $site_url) {
				$source['url'] = str_replace( "https://{$site_url}", $cdn_url, $source_url );
			}
		}
	}
	return $sources;
}

add_filter( 'wp_calculate_image_srcset', 'kate_toms_calculate_image_srcset', 10, 5 );

// Register button form extension
function register_button_form_extension() {
	register_block_type( __DIR__ . '/blocks/button-form-extension' );
}
add_action( 'init', 'register_button_form_extension' );

// Register group link extension
function register_group_link_extension() {
	register_block_type( __DIR__ . '/blocks/group-link-extension' );
}
add_action( 'init', 'register_group_link_extension' );

/**
 * Render linked group blocks with clickable overlay.
 *
 * Adds a clickable overlay link to group blocks that have an href attribute.
 * This makes the entire group block clickable while still allowing inner
 * interactive elements (links, buttons) to function independently.
 *
 * @param string $block_content The block content.
 * @param array  $block         The full block, including name and attributes.
 * @return string Modified block content with link overlay.
 */
function render_linked_group_block( $block_content, $block ) {
	// Only process group blocks.
	if ( 'core/group' !== $block['blockName'] ) {
		return $block_content;
	}

	// Check if the block has a link.
	if ( empty( $block['attrs']['href'] ) ) {
		return $block_content;
	}

	$href = esc_url( $block['attrs']['href'] );
	$target = ! empty( $block['attrs']['linkTarget'] ) ? esc_attr( $block['attrs']['linkTarget'] ) : '';
	$rel = '_blank' === $target ? 'noopener noreferrer' : '';

	// Add has-link class to the group wrapper.
	$block_content = preg_replace(
		'/class="([^"]*wp-block-group[^"]*)"/',
		'class="$1 has-link"',
		$block_content,
		1
	);

	// Build the overlay link attributes.
	$link_attrs = sprintf(
		'href="%s"%s%s',
		$href,
		$target ? sprintf( ' target="%s"', $target ) : '',
		$rel ? sprintf( ' rel="%s"', $rel ) : ''
	);

	// Insert the overlay link as the first child of the group.
	$overlay_link = sprintf(
		'<a %s class="group-link-overlay" aria-label="Link to %s"></a>',
		$link_attrs,
		esc_attr( $href )
	);

	// Find the opening tag and insert after it.
	$block_content = preg_replace(
		'/(<div[^>]*wp-block-group[^>]*>)/',
		'$1' . $overlay_link,
		$block_content,
		1
	);

	return $block_content;
}
add_filter( 'render_block', 'render_linked_group_block', 10, 2 );

/**
 * Enqueue group link extension styles.
 */
function enqueue_group_link_styles() {
	wp_enqueue_style(
		'kate-toms-group-link-styles',
		plugins_url( 'blocks/group-link-extension/style.css', __FILE__ ),
		array(),
		'1.0.0'
	);
}
add_action( 'wp_enqueue_scripts', 'enqueue_group_link_styles' );

// Handle frontend scripts
function enqueue_button_form_scripts() {
	// Only enqueue if we have a button block with form enabled
	//if ( has_block( 'core/button' ) ) {
		wp_enqueue_script(
			'kate-toms-form-handler',
			plugins_url( 'blocks/button-form-extension/view.js', __FILE__ ),
			array( 'jquery' ),
			'1.0.0',
			true
		);

		wp_localize_script(
			'kate-toms-form-handler',
			'ktFormSettings',
			array(
				'nonce'   => wp_create_nonce( 'kt_form_nonce' ),
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			)
		);

		wp_enqueue_style(
			'kate-toms-form-styles',
			plugins_url( 'blocks/button-form-extension/style.css', __FILE__ ),
			array(),
			'1.0.0'
		);
	//}
}
add_action( 'wp_enqueue_scripts', 'enqueue_button_form_scripts' );

// AJAX handler for loading contact form
function load_contact_form_callback() {
	check_ajax_referer( 'kt_form_nonce', 'nonce' );

	ob_start();
	include plugin_dir_path( __FILE__ ) . '../kate-and-toms-get-in-touch/public/partials/kate-and-toms-get-in-touch-form.php';
	$form_html = ob_get_clean();

	wp_send_json_success( array( 'html' => $form_html ) );
}
add_action( 'wp_ajax_load_contact_form', 'load_contact_form_callback' );
add_action( 'wp_ajax_nopriv_load_contact_form', 'load_contact_form_callback' );

// AJAX handler for loading booking form
function load_booking_form_callback() {
	check_ajax_referer( 'kt_form_nonce', 'nonce' );

	ob_start();
	include plugin_dir_path( __FILE__ ) . '../kate-and-toms-get-in-touch/public/partials/kate-and-toms-book-now-form.php';
	$form_html = ob_get_clean();

	wp_send_json_success( array( 'html' => $form_html ) );
}
add_action( 'wp_ajax_load_booking_form', 'load_booking_form_callback' );
add_action( 'wp_ajax_nopriv_load_booking_form', 'load_booking_form_callback' );

// AJAX handler for debug logging from house-landing-pages block editor
function house_landing_debug_log_callback() {
	$query_data = sanitize_text_field( $_POST['query_data'] ?? '' );
	if ( ! empty( $query_data ) ) {
		error_log( 'House Landing Pages Editor Query: ' . $query_data );
	}
	wp_die(); // Always die in AJAX handlers
}
add_action( 'wp_ajax_house_landing_debug_log', 'house_landing_debug_log_callback' );
add_action( 'wp_ajax_nopriv_house_landing_debug_log', 'house_landing_debug_log_callback' );

/**
 * Limit excerpt length to 6 words.
 *
 * @param string $excerpt The excerpt text.
 * @return string The limited excerpt.
 */
function kate_toms_limit_excerpt_words( $excerpt ) {
	$words = explode( ' ', $excerpt );
	if ( count( $words ) > 6 ) {
		$words = array_slice( $words, 0, 6 );
		$excerpt = implode( ' ', $words ) . '... read more&nbsp;&raquo;';
	}
	return $excerpt;
}
add_filter( 'get_the_excerpt', 'kate_toms_limit_excerpt_words' );

/**
 * Register custom image sizes.
 */
function kate_toms_register_image_sizes() {
	add_image_size( 'house_search', 280, 240, true );
	add_image_size( 'cross_promo_wide', 880, 300, true );
	add_image_size( 'cross_promo_wide_prev', 200, 100, true );
	add_image_size( 'cross_promo_narrow', 280, 300, true );
	add_image_size( 'cross_promo_narrow_prev', 100, 150, true );
	add_image_size( 'huge', 1600, 900, true );
	add_image_size( 'square', 580, 580, true );
	add_image_size( 'matrix', 780, 780, true );
	add_image_size( 'square-partners', 550, 450, true );
	add_image_size( 'square-vendor-stats', 470, 365, array( 'center', 'center' ) );
	add_image_size( 'large', 1180, 450, true );
	add_image_size( 'thumbnail', 280, 280, true );
	add_image_size( 'blog-wide', 770, 380, true );
	add_image_size( 'blog-square', 380, 380, true );
	add_image_size( 'blog-square-wide', 280, 188, true );
	add_theme_support( 'post-thumbnails' );
	set_post_thumbnail_size( 1200, 400, true );
}
add_action( 'plugins_loaded', 'kate_toms_register_image_sizes' );

/**
 * Make custom image sizes selectable in the block editor.
 *
 * @param array $sizes Array of image size labels.
 * @return array Modified array of image size labels.
 */
function kate_toms_add_image_size_names( $sizes ) {
	return array_merge(
		$sizes,
		array(
			'house_search'            => __( 'House Search' ),
			'cross_promo_wide'        => __( 'Cross Promo Wide' ),
			'cross_promo_wide_prev'   => __( 'Cross Promo Wide Preview' ),
			'cross_promo_narrow'      => __( 'Cross Promo Narrow' ),
			'cross_promo_narrow_prev' => __( 'Cross Promo Narrow Preview' ),
			'huge'                    => __( 'Huge' ),
			'square'                  => __( 'Square' ),
			'matrix'                  => __( 'Matrix' ),
			'square-partners'         => __( 'Square Partners' ),
			'square-vendor-stats'     => __( 'Square Vendor Stats' ),
			'blog-wide'               => __( 'Blog Wide' ),
			'blog-square'             => __( 'Blog Square' ),
			'blog-square-wide'        => __( 'Blog Square Wide' ),
		)
	);
}
add_filter( 'image_size_names_choose', 'kate_toms_add_image_size_names' );

