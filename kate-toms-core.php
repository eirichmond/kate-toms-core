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

function kate_toms_post_thumbnail_size_filter( $size, $post_id ) {
	$size = 'square';
	return $size;
}
add_filter( 'post_thumbnail_size', 'kate_toms_post_thumbnail_size_filter', 10, 2 );


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


