<?php

/**
 * The file that defines the core plugin class
 *
 * A class definition that includes attributes and functions used across both the
 * public-facing side of the site and the admin area.
 *
 * @link       https://elliottrichmond.co.uk
 * @since      1.0.0
 *
 * @package    Kate_Toms_Core
 * @subpackage Kate_Toms_Core/includes
 */

/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * Also maintains the unique identifier of this plugin as well as the current
 * version of the plugin.
 *
 * @since      1.0.0
 * @package    Kate_Toms_Core
 * @subpackage Kate_Toms_Core/includes
 * @author     Elliott Richmond <elliott@squareonemd.co.uk>
 */
class Kate_Toms_Core {

	/**
	 * The loader that's responsible for maintaining and registering all hooks that power
	 * the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      Kate_Toms_Core_Loader    $loader    Maintains and registers all hooks for the plugin.
	 */
	protected $loader;

	/**
	 * The unique identifier of this plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $plugin_name    The string used to uniquely identify this plugin.
	 */
	protected $plugin_name;

	/**
	 * The current version of the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $version    The current version of the plugin.
	 */
	protected $version;

	/**
	 * Define the core functionality of the plugin.
	 *
	 * Set the plugin name and the plugin version that can be used throughout the plugin.
	 * Load the dependencies, define the locale, and set the hooks for the admin area and
	 * the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function __construct() {
		if ( defined( 'KATE_TOMS_CORE_VERSION' ) ) {
			$this->version = KATE_TOMS_CORE_VERSION;
		} else {
			$this->version = '1.0.0';
		}
		$this->plugin_name = 'kate-toms-core';

		$this->load_dependencies();
		$this->set_locale();
		$this->define_admin_hooks();
		$this->define_public_hooks();

		// Add button block filter
		add_filter( 'render_block', array( $this, 'modify_button_block_html' ), 10, 2 );
	}

	/**
	 * Load the required dependencies for this plugin.
	 *
	 * Include the following files that make up the plugin:
	 *
	 * - Kate_Toms_Core_Loader. Orchestrates the hooks of the plugin.
	 * - Kate_Toms_Core_i18n. Defines internationalization functionality.
	 * - Kate_Toms_Core_Admin. Defines all hooks for the admin area.
	 * - Kate_Toms_Core_Public. Defines all hooks for the public side of the site.
	 *
	 * Create an instance of the loader which will be used to register the hooks
	 * with WordPress.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function load_dependencies() {

		/**
		 * The class responsible for orchestrating the actions and filters of the
		 * core plugin.
		 */
		require_once plugin_dir_path( __DIR__ ) . 'includes/class-kate-toms-core-loader.php';

		/**
		 * The class responsible for defining internationalization functionality
		 * of the plugin.
		 */
		require_once plugin_dir_path( __DIR__ ) . 'includes/class-kate-toms-core-i18n.php';

		/**
		 * The class responsible for defining all actions that occur in the admin area.
		 */
		require_once plugin_dir_path( __DIR__ ) . 'admin/class-kate-toms-core-admin.php';

		/**
		 * The class responsible for defining all actions that occur in the public-facing
		 * side of the site.
		 */
		require_once plugin_dir_path( __DIR__ ) . 'public/class-kate-toms-core-public.php';

		/**
		 * The class responsible for the Houses Filter API functionality
		 */
		require_once plugin_dir_path( __DIR__ ) . 'includes/houses-filter/class-houses-filter-api.php';

		$this->loader = new Kate_Toms_Core_Loader();

		// Initialize the API
		new Houses_Filter_API();
	}

	/**
	 * Define the locale for this plugin for internationalization.
	 *
	 * Uses the Kate_Toms_Core_i18n class in order to set the domain and to register the hook
	 * with WordPress.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function set_locale() {

		$plugin_i18n = new Kate_Toms_Core_i18n();

		$this->loader->add_action( 'plugins_loaded', $plugin_i18n, 'load_plugin_textdomain' );
	}

	/**
	 * Register all of the hooks related to the admin area functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_admin_hooks() {

		$plugin_admin = new Kate_Toms_Core_Admin( $this->get_plugin_name(), $this->get_version() );

		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_styles' );
		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts' );

		$this->loader->add_action( 'init', $plugin_admin, 'register_kateandtoms_core_blocks' );
		$this->loader->add_action( 'init', $plugin_admin, 'custom_post_types_taxonomies_callback' );
		$this->loader->add_action( 'init', $plugin_admin, 'custom_meta_houses_callback' );
		$this->loader->add_action( 'init', $plugin_admin, 'kate_toms_core_register_pattern_categories' );
		$this->loader->add_action( 'init', $plugin_admin, 'kate_toms_core_register_patterns' );
		$this->loader->add_action( 'init', $plugin_admin, 'remove_core_patterns' );

	}

	/**
	 * Register all of the hooks related to the public-facing functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_public_hooks() {

		$plugin_public = new Kate_Toms_Core_Public( $this->get_plugin_name(), $this->get_version() );

		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_styles' );
		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_scripts' );			
		$this->loader->add_action( 'wp_head', $plugin_public, 'bugherd_script' );
		$this->loader->add_filter( 'wp_calculate_image_srcset', $plugin_public, 'kate_toms_replace_image_srcset_url', 10, 5 );

	}

	/**
	 * Run the loader to execute all of the hooks with WordPress.
	 *
	 * @since    1.0.0
	 */
	public function run() {
		$this->loader->run();
	}

	/**
	 * The name of the plugin used to uniquely identify it within the context of
	 * WordPress and to define internationalization functionality.
	 *
	 * @since     1.0.0
	 * @return    string    The name of the plugin.
	 */
	public function get_plugin_name() {
		return $this->plugin_name;
	}

	/**
	 * The reference to the class that orchestrates the hooks with the plugin.
	 *
	 * @since     1.0.0
	 * @return    Kate_Toms_Core_Loader    Orchestrates the hooks of the plugin.
	 */
	public function get_loader() {
		return $this->loader;
	}

	/**
	 * Retrieve the version number of the plugin.
	 *
	 * @since     1.0.0
	 * @return    string    The version number of the plugin.
	 */
	public function get_version() {
		return $this->version;
	}

	/**
	 * Add custom attributes to button block HTML
	 */
	public function modify_button_block_html( $block_content, $block ) {
		if ( $block['blockName'] === 'core/button' && ! empty( $block['attrs']['showForm'] ) ) {
			$dom = new DOMDocument();
			$dom->loadHTML( $block_content, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );

			$button = $dom->getElementsByTagName( 'div' )->item( 0 );
			if ( $button ) {
				$button->setAttribute( 'data-show-form', 'true' );
				$button->setAttribute( 'data-form-type', $block['attrs']['formType'] ?? 'contact' );
			}

			return $dom->saveHTML();
		}
		return $block_content;
	}
}
