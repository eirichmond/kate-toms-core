<?php

/**
 * The admin-specific functionality of the plugin.
 *
 * @link       https://elliottrichmond.co.uk
 * @since      1.0.0
 *
 * @package    Kate_Toms_Core
 * @subpackage Kate_Toms_Core/admin
 */

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    Kate_Toms_Core
 * @subpackage Kate_Toms_Core/admin
 * @author     Elliott Richmond <elliott@squareonemd.co.uk>
 */
class Kate_Toms_Core_Admin {

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
	 * Register the stylesheets for the admin area.
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

		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/kate-toms-core-admin.css', array(), $this->version, 'all' );
	}

	/**
	 * Register the JavaScript for the admin area.
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

		wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/kate-toms-core-admin.js', array( 'jquery' ), $this->version, false );
	}

	/**
	 * Register all kate&toms core blocks from the main plugin folder
	 *
	 * @return void
	 */
	public function register_kateandtoms_core_blocks() {
		$directory_path = plugin_dir_path( __DIR__ ) . '/build';

		// Check if the directory exists
		if ( is_dir( $directory_path ) ) {
			// Scan the directory and get an array of items
			$items = scandir( $directory_path );

			// Filter and list only directories
			$folders = array_filter(
				$items,
				function ( $item ) use ( $directory_path ) {
					return is_dir( $directory_path . DIRECTORY_SEPARATOR . $item ) && $item !== '.' && $item !== '..';
				}
			);

			foreach ( $folders as $folder ) {
				$block_path = plugin_dir_path( __DIR__ ) . '/build/' . $folder;
				error_log( 'Registering block: ' . $folder . ' from path: ' . $block_path );
				
				// Special handling for single-house-display
				if ( $folder === 'single-house-display' ) {
					$result = register_block_type( $block_path, array(
						'render_callback' => function( $attributes ) {
							// Early return if no house is selected
							if (empty($attributes['selectedHouse'])) {
								return '<div class="wp-block-kate-toms-core-single-house-display"><p>' . __('No house selected.', 'kate-toms-core') . '</p></div>';
							}

							$house_id = absint($attributes['selectedHouse']);
							$display_style = sanitize_text_field($attributes['displayStyle'] ?? 'coast');

							// Get the house post
							$house_post = get_post($house_id);
							if (!$house_post || $house_post->post_type !== 'houses') {
								return '<div class="wp-block-kate-toms-core-single-house-display"><p>' . __('House not found.', 'kate-toms-core') . '</p></div>';
							}

							// Check if it's a parent house (not a child page)
							if ($house_post->post_parent != 0) {
								return '<div class="wp-block-kate-toms-core-single-house-display"><p>' . __('Please select a parent house.', 'kate-toms-core') . '</p></div>';
							}

							// Get house meta data
							$brief_description = get_post_meta($house_id, 'brief_description', true);
							$sleeps_min = get_post_meta($house_id, 'sleeps_min', true);
							$sleeps_max = get_post_meta($house_id, 'sleeps_max', true);
							$location_text = get_post_meta($house_id, 'location_text', true);

							// Get featured image
							$featured_image = get_the_post_thumbnail($house_id, 'full');
							$house_url = get_permalink($house_id);

							// Set background color based on display style
							$background_colors = [
								'coast' => 'coloreight',
								'cotswolds' => 'colorfive',
								'country' => 'titlecolorthree',
								'town' => 'coloreight',
							];

							$background_color = $background_colors[$display_style] ?? 'coloreight';

							// Build the HTML using the pattern structure
							$html = '<div class="wp-block-kate-toms-core-single-house-display">';
							$html .= '<div class="wp-block-group has-white-background-color has-background" style="min-height:365px">';

							// Featured image with link
							if ($featured_image) {
								$html .= '<a href="' . esc_url($house_url) . '">' . $featured_image . '</a>';
							}

							// Title with link and background color
							$html .= '<div class="wp-block-post-title has-text-align-center">';
							$html .= '<h2 class="wp-block-post-title__link has-' . esc_attr($background_color) . '-background-color has-white-color has-background has-link-color" style="padding-top:var(--wp--preset--spacing--40);padding-bottom:var(--wp--preset--spacing--40);font-style:normal;font-weight:600;font-size:var(--wp--preset--font-size--small)">';
							$html .= '<a href="' . esc_url($house_url) . '" style="color:var(--wp--preset--color--white)">' . esc_html($house_post->post_title) . '</a>';
							$html .= '</h2>';
							$html .= '</div>';

							// Description section
							$html .= '<div class="wp-block-group" style="padding-top:var(--wp--preset--spacing--30);padding-right:var(--wp--preset--spacing--30);padding-bottom:var(--wp--preset--spacing--30);padding-left:var(--wp--preset--spacing--30)">';
							$html .= '<p class="has-x-small-font-size">' . esc_html($brief_description) . '</p>';
							$html .= '</div>';

							// Footer section with sleeps and location
							$html .= '<div class="wp-block-group" style="border-top-color:var(--wp--preset--color--tertiary);border-top-width:1px;padding-top:var(--wp--preset--spacing--30);padding-right:var(--wp--preset--spacing--30);padding-bottom:var(--wp--preset--spacing--30);padding-left:var(--wp--preset--spacing--30)">';
							$html .= '<div class="wp-block-group" style="display:flex;flex-wrap:nowrap;justify-content:space-between">';

							// Sleeps section
							$html .= '<div class="wp-block-group" style="display:flex;flex-wrap:nowrap;gap:0.2em">';
							$html .= '<p class="has-x-small-font-size">Sleeps </p>';
							$html .= '<p class="has-x-small-font-size">' . esc_html($sleeps_min) . '</p>';
							$html .= '<p class="has-x-small-font-size"> to </p>';
							$html .= '<p class="has-x-small-font-size">' . esc_html($sleeps_max) . '</p>';
							$html .= '</div>';

							// Location section
							$html .= '<div class="wp-block-group" style="display:flex;flex-wrap:nowrap;justify-content:right">';
							$html .= '<p class="has-text-align-right has-x-small-font-size">' . esc_html($location_text) . '</p>';
							$html .= '</div>';

							$html .= '</div>'; // Close flex container
							$html .= '</div>'; // Close footer group
							$html .= '</div>'; // Close main group
							$html .= '</div>'; // Close wrapper

							return $html;
						}
					) );
				} else {
					$result = register_block_type( $block_path );
				}
				
				if ( ! $result ) {
					error_log( 'Failed to register block: ' . $folder );
				} else {
					error_log( 'Successfully registered block: ' . $folder );
				}
			}
		} else {
			error_log( 'The specified path to block directory is not a valid of found directory.' );
		}
	}

	/**
	 * Register custom post types and taxonomies
	 */
	public function custom_post_types_taxonomies_callback() {

		/**
		 * Register Taxonomies
		 * Locations
		 */

		$labels = array(
			'name'                       => _x( 'Locations', 'taxonomy general name' ),
			'singular_name'              => _x( 'Location', 'taxonomy singular name' ),
			'search_items'               => __( 'Search Locations' ),
			'popular_items'              => __( 'Popular Locations' ),
			'all_items'                  => __( 'All Locations' ),
			'parent_item'                => null,
			'parent_item_colon'          => null,
			'edit_item'                  => __( 'Edit Location' ),
			'update_item'                => __( 'Update Location' ),
			'add_new_item'               => __( 'Add New Location' ),
			'new_item_name'              => __( 'New Location Name' ),
			'separate_items_with_commas' => __( 'Separate locations with commas' ),
			'add_or_remove_items'        => __( 'Add or remove locations' ),
			'choose_from_most_used'      => __( 'Choose from the most used locations' ),
			'menu_name'                  => __( 'Locations' ),
		);

		register_taxonomy(
			'location',
			'houses',
			array(
				'hierarchical'      => true,
				'labels'            => $labels,
				'show_in_rest'      => true,
				'show_ui'           => true,
				'query_var'         => true,
				'show_admin_column' => true,
				'rewrite'           => array( 'slug' => 'location' ),
			)
		);

		/**
		 * Register Taxonomies
		 * Sizes
		 */

		$labels = array(
			'name'                       => _x( 'Size Searches', 'taxonomy general name' ),
			'singular_name'              => _x( 'Size Search', 'taxonomy singular name' ),
			'search_items'               => __( 'Search Sizes' ),
			'popular_items'              => __( 'Popular Sizes' ),
			'all_items'                  => __( 'All Sizes' ),
			'parent_item'                => null,
			'parent_item_colon'          => null,
			'edit_item'                  => __( 'Edit Search Size' ),
			'update_item'                => __( 'Update Size' ),
			'add_new_item'               => __( 'Add New Size' ),
			'new_item_name'              => __( 'New Size Title' ),
			'separate_items_with_commas' => __( 'Separate sizes with commas' ),
			'add_or_remove_items'        => __( 'Add or remove sizes' ),
			'choose_from_most_used'      => __( 'Choose from the most used sizes' ),
			'menu_name'                  => __( 'Search Sizes' ),
		);

		register_taxonomy(
			'size',
			'houses',
			array(
				'hierarchical'      => true,
				'labels'            => $labels,
				'show_in_rest'      => true,
				'show_ui'           => true,
				'query_var'         => true,
				'show_admin_column' => true,
				'rewrite'           => array( 'slug' => 'size' ),
			)
		);

		/**
		 * Register Taxonomies
		 * Activities
		 */

		$labels = array(
			'name'                       => _x( 'Activities', 'taxonomy general name' ),
			'singular_name'              => _x( 'Activity', 'taxonomy singular name' ),
			'search_items'               => __( 'Search Activities' ),
			'popular_items'              => __( 'Popular Activities' ),
			'all_items'                  => __( 'All Activities' ),
			'parent_item'                => null,
			'parent_item_colon'          => null,
			'edit_item'                  => __( 'Edit Activity' ),
			'update_item'                => __( 'Update Activity' ),
			'add_new_item'               => __( 'Add New Activity' ),
			'new_item_name'              => __( 'New Activity Name' ),
			'separate_items_with_commas' => __( 'Separate activities with commas' ),
			'add_or_remove_items'        => __( 'Add or remove activities' ),
			'choose_from_most_used'      => __( 'Choose from most used activities' ),
			'menu_name'                  => __( 'Activities' ),
		);

		register_taxonomy(
			'activity',
			'houses',
			array(
				'hierarchical' => true,
				'labels'       => $labels,
				'show_in_rest' => true,
				'show_ui'      => true,
				'query_var'    => true,
				'rewrite'      => array( 'slug' => 'activity' ),
			)
		);

		/**
		 * Register Taxonomies
		 * Types
		 */

		$labels = array(
			'name'                       => _x( 'Types', 'taxonomy general name' ),
			'singular_name'              => _x( 'Type', 'taxonomy singular name' ),
			'search_items'               => __( 'Search Types' ),
			'popular_items'              => __( 'Popular Types' ),
			'all_items'                  => __( 'All Types' ),
			'parent_item'                => null,
			'parent_item_colon'          => null,
			'edit_item'                  => __( 'Edit Type' ),
			'update_item'                => __( 'Update Type' ),
			'add_new_item'               => __( 'Add New Type' ),
			'new_item_name'              => __( 'New Type Name' ),
			'separate_items_with_commas' => __( 'Separate types with commas' ),
			'add_or_remove_items'        => __( 'Add or remove types' ),
			'choose_from_most_used'      => __( 'Choose from the most used types' ),
			'menu_name'                  => __( 'Types' ),
		);

		register_taxonomy(
			'types',
			'houses',
			array(
				'hierarchical'      => true,
				'labels'            => $labels,
				'show_in_rest'      => true,
				'show_ui'           => true,
				'query_var'         => true,
				'show_admin_column' => true,
				'rewrite'           => array( 'slug' => 'types' ),
			)
		);

		/**
		 * Register Taxonomies
		 * Occasions
		 */
		$labels = array(
			'name'                       => _x( 'Occasions', 'taxonomy general name' ),
			'singular_name'              => _x( 'Occasion', 'taxonomy singular name' ),
			'search_items'               => __( 'Search Occasions' ),
			'popular_items'              => __( 'Popular Occasions' ),
			'all_items'                  => __( 'All Occasions' ),
			'parent_item'                => null,
			'parent_item_colon'          => null,
			'edit_item'                  => __( 'Edit Occasion' ),
			'update_item'                => __( 'Update Occasion' ),
			'add_new_item'               => __( 'Add New Occasion' ),
			'new_item_name'              => __( 'New Occasion Name' ),
			'separate_items_with_commas' => __( 'Separate Occasions with commas' ),
			'add_or_remove_items'        => __( 'Add or remove Occasions' ),
			'choose_from_most_used'      => __( 'Choose from the most used Occasions' ),
			'menu_name'                  => __( 'Occasions' ),
		);

		register_taxonomy(
			'occasion',
			'houses',
			array(
				'hierarchical'      => true,
				'labels'            => $labels,
				'show_in_rest'      => true,
				'show_ui'           => true,
				'query_var'         => true,
				'show_admin_column' => true,
				'rewrite'           => array( 'slug' => 'occasion' ),
			)
		);

		/**
		 * Register Taxonomies
		 * Features
		 */

		$labels = array(
			'name'                       => _x( 'Features', 'taxonomy general name' ),
			'singular_name'              => _x( 'Feature', 'taxonomy singular name' ),
			'search_items'               => __( 'Search Features' ),
			'popular_items'              => __( 'Popular Features' ),
			'all_items'                  => __( 'All Features' ),
			'parent_item'                => null,
			'parent_item_colon'          => null,
			'edit_item'                  => __( 'Edit Feature' ),
			'update_item'                => __( 'Update Feature' ),
			'add_new_item'               => __( 'Add New Feature' ),
			'new_item_name'              => __( 'New Feature Name' ),
			'separate_items_with_commas' => __( 'Separate features with commas' ),
			'add_or_remove_items'        => __( 'Add or remove features' ),
			'choose_from_most_used'      => __( 'Choose from the most used features' ),
			'menu_name'                  => __( 'Features' ),
		);
		register_taxonomy(
			'feature',
			'houses',
			array(
				'hierarchical'      => true,
				'labels'            => $labels,
				'show_in_rest'      => true,
				'show_ui'           => true,
				'query_var'         => true,
				'show_admin_column' => true,
				'rewrite'           => array( 'slug' => 'feature' ),
			)
		);

		/**
		 * Houses post type
		 */
		$labels = array(
			'name'               => _x( 'Houses', 'post type general name' ),
			'singular_name'      => _x( 'House', 'post type singular name' ),
			'add_new'            => _x( 'Add New', 'houses', 'houses' ),
			'add_new_item'       => __( 'Add New House', 'houses' ),
			'edit_item'          => __( 'Edit House', 'houses' ),
			'new_item'           => __( 'New House', 'houses' ),
			'all_items'          => __( 'All Houses', 'houses' ),
			'view_item'          => __( 'View House', 'houses' ),
			'search_items'       => __( 'Search Houses', 'houses' ),
			'not_found'          => __( 'No houses found', 'houses' ),
			'not_found_in_trash' => __( 'No houses found in Trash', 'houses' ),
			'parent_item_colon'  => '',
			'menu_name'          => __( 'Houses', 'houses' ),
		);
		$args   = array(
			'labels'             => $labels,
			'public'             => true,
			'has_archive'        => true,
			'hierarchical'       => true, // Enables parent-child relationships
			'rewrite'            => array( 'slug' => 'houses' ),
			'supports'           => array( 'title', 'editor', 'author', 'revisions', 'custom-fields', 'thumbnail', 'page-attributes' ),
			'show_in_rest'       => true,
			'rest_base'          => 'houses',
			'menu_icon'          => 'dashicons-layout',
			'capability_type'    => 'post',
			'show_ui'            => true,
			'show_in_menu'       => true,
			'query_var'          => true,
			'publicly_queryable' => true,
			'taxonomies'         => array( 'size', 'feature', 'location', 'activity', 'type', 'occasion' ),
		);
		register_post_type( 'houses', $args );

		/**
		 * Availability
		 */
			$labels = array(
				'name'               => _x( 'Availability Periods', 'post type general name' ),
				'singular_name'      => _x( 'Availability Period', 'post type singular name' ),
				'add_new'            => _x( 'Add New', 'houses', 'availability' ),
				'add_new_item'       => __( 'Add New Period', 'availability' ),
				'edit_item'          => __( 'Edit Period', 'availability' ),
				'new_item'           => __( 'New Period', 'availability' ),
				'all_items'          => __( 'All Availability Periods', 'availability' ),
				'view_item'          => __( 'View Period', 'availability' ),
				'search_items'       => __( 'Search Availability Periods', 'availability' ),
				'not_found'          => __( 'No Availability Periods found', 'houses' ),
				'not_found_in_trash' => __( 'No periods found in Trash', 'availability' ),
				'parent_item_colon'  => '',
				'menu_name'          => __( 'Avail. Periods', 'houses' ),
			);
			$args   = array(
				'labels'             => $labels,
				'public'             => true,
				'publicly_queryable' => true,
				'show_ui'            => true,
				'show_in_menu'       => true,
				'query_var'          => true,
				'rewrite'            => true,
				'has_archive'        => false,
				'capability_type'    => 'page',
				'supports'           => array( 'title', 'editor', 'thumbnail' ),
			);
			register_post_type( 'availability', $args );

			/**
			 * Seasonal
			 */
			$labels = array(
				'name'               => _x( 'Seasonal Periods', 'post type general name' ),
				'singular_name'      => _x( 'Seasonal Period', 'post type singular name' ),
				'add_new'            => _x( 'Add New', 'houses', 'seasonal' ),
				'add_new_item'       => __( 'Add New Period', 'seasonal' ),
				'edit_item'          => __( 'Edit Period', 'seasonal' ),
				'new_item'           => __( 'New Period', 'seasonal' ),
				'all_items'          => __( 'All Seasonal Periods', 'seasonal' ),
				'view_item'          => __( 'View Period', 'seasonal' ),
				'search_items'       => __( 'Search Seasonal Periods', 'seasonal' ),
				'not_found'          => __( 'No seasonal periods found', 'houses' ),
				'not_found_in_trash' => __( 'No periods found in Trash', 'seasonal' ),
				'parent_item_colon'  => '',
				'menu_name'          => __( 'Seasonal Periods', 'houses' ),
			);
			$args   = array(
				'labels'             => $labels,
				'public'             => true,
				'publicly_queryable' => true,
				'show_ui'            => true,
				'show_in_menu'       => true,
				'query_var'          => true,
				'rewrite'            => true,
				'has_archive'        => false,
				'capability_type'    => 'page',
				'supports'           => array( 'title', 'editor', 'thumbnail' ),
			);
			register_post_type( 'seasonal', $args );

			// $areas = array( 'houses' );

			// $options = get_option( 'plugin_options' );
			// if ( isset( $options['activate_suppliers'] ) ) {
			// if ( $options['activate_suppliers'] ) {
			// array_push( $areas, 'suppliers' );
			// }
			// }
	}

	/**
	 * Register custom meta for houses
	 */
	public function custom_meta_houses_callback() {

		$metas = array(
			'sleeps_min',
			'sleeps_max',
			'location_text',
			'brief_description',
		);

		/**
		 * Register the meta
		 *
		 * @param string $object_type
		 * @param string $meta_key
		 * @param array $args
		 *
		 * @return void
		 */
		foreach ( $metas as $meta ) {
			// code...
			register_meta(
				'post',
				$meta,
				array(
					'object_subtype'    => 'houses',
					'show_in_rest'      => true,
					'single'            => true,
					'type'              => 'string',
					'sanitize_callback' => 'wp_strip_all_tags',
				)
			);
		}
	}



	/**
	 * Register pattern categories
	 *
	 * @return void
	 */
	public function kate_toms_core_register_pattern_categories() {
		register_block_pattern_category(
			'house-card-search',
			array(
				'label'       => 'House Card Search',
				'description' => 'House card search patterns',
			)
		);
		
		register_block_pattern_category(
			'calendar-booking',
			array(
				'label'       => 'Calendar & Booking',
				'description' => 'Calendar and booking related patterns',
			)
		);

		register_block_pattern_category(
			'kate-and-toms',
			array(
				'label'       => 'Kate & Toms',
				'description' => 'Kate & Toms related patterns',
			)
		);
	}

	/**
	 * Register patterns
	 *
	 * @return void
	 */
	public function kate_toms_core_register_patterns() {
		register_block_pattern(
			'kate-toms-core/house-card-test',
			array(
				'title' => 'House Card Test',
				'categories' => array( 'house-card-search' ),
				'viewportWidth' => 1500,
				'filePath' => plugin_dir_path( __FILE__ ) . 'partials/patterns/house-card-test.php',
			)
		);
		
		register_block_pattern(
			'kate-toms-core/calendar-booking-setup',
			array(
				'title' => 'Calendar Booking Setup',
				'description' => 'Complete calendar setup with heading and availability notes',
				'categories' => array( 'calendar-booking' ),
				'keywords' => array( 'calendar', 'booking', 'availability', 'dates' ),
				'viewportWidth' => 1500,
				'filePath' => plugin_dir_path( __FILE__ ) . 'partials/patterns/calendar-booking-setup.php',
			)
		);
	}

	/**
	 * Remove core patterns
	 *
	 * @return void
	 */
	public function remove_core_patterns() {
		remove_theme_support( 'core-block-patterns' );
	}
}
