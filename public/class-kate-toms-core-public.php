<?php

/**
 * The public-specific functionality of the plugin.
 *
 * @link       https://elliottrichmond.co.uk
 * @since      1.0.0
 *
 * @package    Kate_Toms_Core
 * @subpackage Kate_Toms_Core/public
 */

/**
 * The public-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the public-specific stylesheet and JavaScript.
 *
 * @package    Kate_Toms_Core
 * @subpackage Kate_Toms_Core/public
 * @author     Elliott Richmond <elliott@squareonemd.co.uk>
 */
class Kate_Toms_Core_Public {

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param      string $plugin_name       The name of this plugin.
	 * @param      string $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version     = $version;
	}

	/**
	 * Register the stylesheets for the public area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles() {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Kate_Toms_Core_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Kate_Toms_Core_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/kate-toms-core-public.css', array(), $this->version, 'all' );
	}

	/**
	 * Register the JavaScript for the public area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts() {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Kate_Toms_Core_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Kate_Toms_Core_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/kate-toms-core-public.js', array( 'jquery' ), $this->version, false );
	}
	


	

	/**
	 * Helper Functions
	 *
	 * The functions below are related to testing or can be considered
	 * as temporary helper functions for development purposes. They
	 * may potentially be removed in future versions of the plugin.
	 *
	 * @since 1.0.0
	 */

	/**
	 * Outputs the BugHerd tracking script in the frontend.
	 *
	 * Note: This is a development tool and should be properly managed via wp_enqueue_script().
	 * Consider refactoring to use WordPress enqueue system for better practice.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function bugherd_script() {
		echo '<script type="text/javascript" src="https://www.bugherd.com/sidebarv2.js?apikey=8je1j3guc7qdsjlkvgxhyq" async="true"></script>';
	}

	/**
	 * Modifies featured image HTML to handle URL differences between environments.
	 *
	 * @since 1.0.0
	 * @param string $html               The featured image HTML.
	 * @param int    $post_id           The post ID.
	 * @param int    $post_thumbnail_id The post thumbnail ID.
	 * @param string $size              The requested image size.
	 * @param array  $attr              Array of image attributes.
	 * @return string Modified featured image HTML.
	 */
	public function modify_featured_image_html_local_staging_production( $html, $post_id, $post_thumbnail_id, $size, $attr ) {
		// Modify the HTML here
		$html = str_replace(
			[ 'bigholidayhomes.co.uk', 'http://kateandtomsblocks.test', 'blogs.dir/11/files'],
			[ 'kateandtoms.com', 'https://kateandtoms.com', 'uploads'],
			$html
		);
		return $html;
	}

	/**
	 * Replaces the domain in image srcset URLs with the CDN domain.
	 *
	 * @since 1.0.0
	 * @param array  $sources      The array of image sources for the srcset.
	 * @param array  $size_array   Array of width and height values in pixels (in that order).
	 * @param string $image_src    The image source URL.
	 * @param array  $image_meta   The image meta data.
	 * @param int    $attachment_id The image attachment ID.
	 * @return array Modified array of image sources with CDN URLs.
	 */
	public function kate_toms_replace_image_srcset_url( $sources, $size_array, $image_src, $image_meta, $attachment_id ) {
		foreach ( $sources as &$source ) {
			$source['url'] = str_replace( '//kateandtomsblocks.test', '//kateandtoms.com', $source['url'] );
		}
		return $sources;
	}

}
