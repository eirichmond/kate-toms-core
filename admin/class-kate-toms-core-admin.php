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
	public function enqueue_scripts( $hook ) {

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

		// Enqueue media scripts on House Settings page
		if ( 'settings_page_house-settings' === $hook ) {
			wp_enqueue_media();
		}

		// Enqueue seasonal meta sidebar for block editor
		if ( 'post.php' === $hook || 'post-new.php' === $hook ) {
			$screen = get_current_screen();
			if ( $screen && 'seasonal' === $screen->post_type ) {
				wp_enqueue_script(
					'seasonal-meta-sidebar',
					plugin_dir_url( __FILE__ ) . 'js/seasonal-meta-sidebar.js',
					array(
						'wp-plugins',
						'wp-editor',
						'wp-element',
						'wp-components',
						'wp-data',
						'wp-i18n',
					),
					$this->version,
					true
				);
			}

			// Enqueue availability meta sidebar for block editor
			if ( $screen && 'availability' === $screen->post_type ) {
				wp_enqueue_script(
					'availability-meta-sidebar',
					plugin_dir_url( __FILE__ ) . 'js/availability-meta-sidebar.js',
					array(
						'wp-plugins',
						'wp-editor',
						'wp-element',
						'wp-components',
						'wp-data',
						'wp-i18n',
					),
					$this->version,
					true
				);
			}

			// Enqueue houses meta sidebar for block editor
			if ( $screen && 'houses' === $screen->post_type ) {
				wp_enqueue_script(
					'houses-meta-sidebar',
					plugin_dir_url( __FILE__ ) . 'js/houses-meta-sidebar.js',
					array(
						'wp-plugins',
						'wp-editor',
						'wp-element',
						'wp-components',
						'wp-data',
						'wp-i18n',
					),
					$this->version,
					true
				);
			}
		}
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

			// Folders under build/ that are NOT real blocks and must be skipped
			// by the auto-discovery registration.
			//
			// mobile-nav-drilldown is a build-pipeline stub: its block.json exists
			// only so wp-scripts bundles view.js + style.css for the mobile drilldown
			// enhancement of the core/navigation block. It has no editor UI, no
			// render callback, and must never appear in the inserter.
			$skip_folders = array( 'mobile-nav-drilldown', 'blueprint-admin' );

			foreach ( $folders as $folder ) {
				if ( in_array( $folder, $skip_folders, true ) ) {
					continue;
				}

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
			'type',
			'houses',
			array(
				'hierarchical'      => true,
				'labels'            => $labels,
				'show_in_rest'      => true,
				'rest_base'         => 'house-types',
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
				'supports'           => array( 'title', 'editor', 'thumbnail', 'custom-fields' ),
				'show_in_rest'       => true,
				'rest_base'          => 'availability',
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
				'supports'           => array( 'title', 'editor', 'thumbnail', 'custom-fields' ),
				'show_in_rest'       => true,
				'rest_base'          => 'seasonal',
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

		// Register signature collection enabled meta field
		register_post_meta(
			'houses',
			'_signature_collection_enabled',
			array(
				'show_in_rest'      => true,
				'single'            => true,
				'type'              => 'string',
				'default'           => '0',
				'sanitize_callback' => function( $value ) {
					return in_array( $value, array( '0', '1' ), true ) ? $value : '0';
				},
				'auth_callback'     => function() {
					return current_user_can( 'edit_posts' );
				},
			)
		);
	}

	/**
	 * The house detail meta fields surfaced in the editor meta box.
	 *
	 * Keyed by meta key, value is the field config used for rendering/saving.
	 *
	 * @return array
	 */
	private function house_detail_fields() {
		return array(
			'sleeps_min'        => array(
				'label' => __( 'Sleeps (min)', 'kate-toms-core' ),
				'type'  => 'number',
			),
			'sleeps_max'        => array(
				'label' => __( 'Sleeps (max)', 'kate-toms-core' ),
				'type'  => 'number',
			),
			'location_text'     => array(
				'label' => __( 'Location text', 'kate-toms-core' ),
				'type'  => 'text',
			),
			'brief_description' => array(
				'label' => __( 'Brief description', 'kate-toms-core' ),
				'type'  => 'textarea',
			),
		);
	}

	/**
	 * Register the "House details" meta box on the houses edit screen.
	 *
	 * @return void
	 */
	public function add_houses_meta_box() {
		add_meta_box(
			'kate_toms_house_details',
			__( 'House details', 'kate-toms-core' ),
			array( $this, 'render_houses_meta_box' ),
			'houses',
			'side',
			'default'
		);
	}

	/**
	 * Render the "House details" meta box fields.
	 *
	 * @param WP_Post $post Current post object.
	 * @return void
	 */
	public function render_houses_meta_box( $post ) {
		wp_nonce_field( 'kate_toms_house_details_save', 'kate_toms_house_details_nonce' );

		foreach ( $this->house_detail_fields() as $key => $field ) {
			$value    = get_post_meta( $post->ID, $key, true );
			$field_id = 'kt-house-' . $key;
			?>
			<p>
				<label for="<?php echo esc_attr( $field_id ); ?>" style="display:block;font-weight:600;margin-bottom:4px;">
					<?php echo esc_html( $field['label'] ); ?>
				</label>
				<?php if ( 'textarea' === $field['type'] ) : ?>
					<textarea id="<?php echo esc_attr( $field_id ); ?>" name="<?php echo esc_attr( $key ); ?>" rows="4" class="widefat"><?php echo esc_textarea( $value ); ?></textarea>
				<?php elseif ( 'number' === $field['type'] ) : ?>
					<input type="number" min="0" step="1" id="<?php echo esc_attr( $field_id ); ?>" name="<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( $value ); ?>" class="widefat" />
				<?php else : ?>
					<input type="text" id="<?php echo esc_attr( $field_id ); ?>" name="<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( $value ); ?>" class="widefat" />
				<?php endif; ?>
			</p>
			<?php
		}
	}

	/**
	 * Persist the "House details" meta box fields.
	 *
	 * @param int $post_id Post being saved.
	 * @return void
	 */
	public function save_houses_meta_box( $post_id ) {
		if ( ! isset( $_POST['kate_toms_house_details_nonce'] )
			|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['kate_toms_house_details_nonce'] ) ), 'kate_toms_house_details_save' ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		foreach ( $this->house_detail_fields() as $key => $field ) {
			if ( ! isset( $_POST[ $key ] ) ) {
				continue;
			}

			$raw = wp_unslash( $_POST[ $key ] );

			if ( 'number' === $field['type'] ) {
				$value = ( '' === trim( $raw ) ) ? '' : (string) absint( $raw );
			} elseif ( 'textarea' === $field['type'] ) {
				$value = sanitize_textarea_field( $raw );
			} else {
				$value = sanitize_text_field( $raw );
			}

			update_post_meta( $post_id, $key, $value );
		}
	}

	/**
	 * Register custom meta for seasonal post type
	 *
	 * @return void
	 */
	public function custom_meta_seasonal_callback() {
		register_post_meta(
			'seasonal',
			'beginning',
			array(
				'show_in_rest'      => true,
				'single'            => true,
				'type'              => 'string',
				'default'           => '',
				'sanitize_callback' => 'sanitize_text_field',
				'auth_callback'     => function() {
					return current_user_can( 'edit_posts' );
				},
			)
		);

		register_post_meta(
			'seasonal',
			'ending',
			array(
				'show_in_rest'      => true,
				'single'            => true,
				'type'              => 'string',
				'default'           => '',
				'sanitize_callback' => 'sanitize_text_field',
				'auth_callback'     => function() {
					return current_user_can( 'edit_posts' );
				},
			)
		);

		register_post_meta(
			'seasonal',
			'periods_to_include',
			array(
				'show_in_rest'      => array(
					'schema' => array(
						'type'    => 'array',
						'items'   => array(
							'type' => 'string',
						),
						'default' => array(),
					),
				),
				'single'            => true,
				'type'              => 'array',
				'default'           => array(),
				'auth_callback'     => function() {
					return current_user_can( 'edit_posts' );
				},
			)
		);
	}

	/**
	 * Register custom meta for availability post type
	 *
	 * @return void
	 */
	public function custom_meta_availability_callback() {
		register_post_meta(
			'availability',
			'rolling_upcoming_period',
			array(
				'show_in_rest'      => true,
				'single'            => true,
				'type'              => 'string',
				'default'           => '6',
				'sanitize_callback' => 'sanitize_text_field',
				'auth_callback'     => function() {
					return current_user_can( 'edit_posts' );
				},
			)
		);

		register_post_meta(
			'availability',
			'periods_to_include',
			array(
				'show_in_rest'      => array(
					'schema' => array(
						'type'    => 'array',
						'items'   => array(
							'type' => 'string',
						),
						'default' => array(),
					),
				),
				'single'            => true,
				'type'              => 'array',
				'default'           => array(),
				'auth_callback'     => function() {
					return current_user_can( 'edit_posts' );
				},
			)
		);
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

	/**
	 * Add admin menu for Adverts
	 */
	public function add_adverts_admin_menu() {
		add_submenu_page(
			'edit.php?post_type=houses',
			'House Adverts',
			'House Adverts',
			'manage_options',
			'house-adverts',
			array( $this, 'adverts_admin_page' )
		);
	}

	/**
	 * Add House Settings page under Settings menu
	 */
	public function add_house_settings_menu() {
		add_options_page(
			'House Settings',
			'House Settings',
			'manage_options',
			'house-settings',
			array( $this, 'house_settings_page' )
		);
	}

	/**
	 * Register House Settings
	 */
	public function register_house_settings() {
		register_setting(
			'house_settings_group',
			'signature_collection_badge_id',
			array(
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
				'default'           => 0,
			)
		);

		add_settings_section(
			'signature_collection_section',
			'Signature Collection Badge',
			array( $this, 'signature_collection_section_callback' ),
			'house-settings'
		);

		add_settings_field(
			'signature_collection_badge',
			'Badge Image',
			array( $this, 'signature_collection_badge_callback' ),
			'house-settings',
			'signature_collection_section'
		);
	}

	/**
	 * Signature Collection section description
	 */
	public function signature_collection_section_callback() {
		echo '<p>Upload or select an image to be displayed on houses marked as part of the Signature Collection.</p>';
	}

	/**
	 * Signature Collection Badge field callback
	 */
	public function signature_collection_badge_callback() {
		$image_id = get_option( 'signature_collection_badge_id', 0 );
		$image_url = $image_id ? wp_get_attachment_image_url( $image_id, 'medium' ) : '';
		?>
		<div class="signature-collection-badge-field">
			<input type="hidden" id="signature_collection_badge_id" name="signature_collection_badge_id" value="<?php echo esc_attr( $image_id ); ?>" />

			<div class="signature-badge-preview" style="margin-bottom: 10px;">
				<?php if ( $image_url ) : ?>
					<img src="<?php echo esc_url( $image_url ); ?>" style="max-width: 200px; height: auto; display: block;" alt="Signature Collection Badge" />
				<?php else : ?>
					<p style="color: #666;">No image selected</p>
				<?php endif; ?>
			</div>

			<button type="button" class="button button-secondary signature-badge-upload" id="signature_badge_upload_button">
				<?php echo $image_id ? 'Change Image' : 'Select Image'; ?>
			</button>

			<?php if ( $image_id ) : ?>
				<button type="button" class="button button-link-delete signature-badge-remove" id="signature_badge_remove_button" style="color: #d63638; margin-left: 10px;">
					Remove Image
				</button>
			<?php endif; ?>

			<p class="description">This image will appear in the bottom-right corner of the Image Fader block for houses with Signature Collection enabled.</p>
		</div>

		<script type="text/javascript">
		jQuery(document).ready(function($) {
			var mediaUploader;

			$('#signature_badge_upload_button').on('click', function(e) {
				e.preventDefault();

				if (mediaUploader) {
					mediaUploader.open();
					return;
				}

				mediaUploader = wp.media({
					title: 'Select Signature Collection Badge',
					button: {
						text: 'Use This Image'
					},
					multiple: false,
					library: {
						type: 'image'
					}
				});

				mediaUploader.on('select', function() {
					var attachment = mediaUploader.state().get('selection').first().toJSON();
					$('#signature_collection_badge_id').val(attachment.id);
					$('.signature-badge-preview').html(
						'<img src="' + attachment.url + '" style="max-width: 200px; height: auto; display: block;" alt="Signature Collection Badge" />'
					);
					$('#signature_badge_upload_button').text('Change Image');
					if ($('#signature_badge_remove_button').length === 0) {
						$('#signature_badge_upload_button').after(
							'<button type="button" class="button button-link-delete signature-badge-remove" id="signature_badge_remove_button" style="color: #d63638; margin-left: 10px;">Remove Image</button>'
						);
					}
				});

				mediaUploader.open();
			});

			$(document).on('click', '#signature_badge_remove_button', function(e) {
				e.preventDefault();
				$('#signature_collection_badge_id').val('');
				$('.signature-badge-preview').html('<p style="color: #666;">No image selected</p>');
				$('#signature_badge_upload_button').text('Select Image');
				$(this).remove();
			});
		});
		</script>
		<?php
	}

	/**
	 * Display House Settings page
	 */
	public function house_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
		}
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<form method="post" action="options.php">
				<?php
				settings_fields( 'house_settings_group' );
				do_settings_sections( 'house-settings' );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Add signature collection badge to Image Fader block
	 *
	 * @param string $block_content The block content.
	 * @param array  $block The full block, including name and attributes.
	 * @return string Modified block content with badge overlay.
	 */
	public function add_signature_collection_badge( $block_content, $block ) {
		// Only process kateandtoms-image-fader blocks
		if ( 'create-block/kateandtoms-image-fader' !== $block['blockName'] ) {
			return $block_content;
		}

		// Get the current post ID
		$post_id = get_the_ID();
		if ( ! $post_id ) {
			return $block_content;
		}

		// Check if this is a house post type
		if ( 'houses' !== get_post_type( $post_id ) ) {
			return $block_content;
		}

		// Check if signature collection is enabled for this house
		$signature_enabled = get_post_meta( $post_id, '_signature_collection_enabled', true );
		if ( '1' !== $signature_enabled ) {
			return $block_content;
		}

		// Get the badge image
		$badge_id = get_option( 'signature_collection_badge_id', 0 );
		if ( ! $badge_id ) {
			return $block_content;
		}

		$badge_url = wp_get_attachment_image_url( $badge_id, 'medium' );
		if ( ! $badge_url ) {
			return $block_content;
		}

		// Add the badge overlay to the block content
		$badge_html = sprintf(
			'<div class="signature-collection-badge"><img src="%s" alt="Signature Collection" /></div>',
			esc_url( $badge_url )
		);

		// Insert the badge after the opening div of the block
		$block_content = preg_replace(
			'/(<div[^>]*class="[^"]*wp-block-create-block-kateandtoms-image-fader[^"]*"[^>]*>)/',
			'$1' . $badge_html,
			$block_content,
			1
		);

		return $block_content;
	}

	/**
	 * Display adverts admin page
	 */
	public function adverts_admin_page() {
		// Handle form submission
		if ( isset( $_POST['upload_advert'] ) && wp_verify_nonce( $_POST['advert_nonce'], 'upload_advert' ) ) {
			$this->handle_advert_upload();
		}

		// Handle deletion
		if ( isset( $_POST['delete_advert'] ) && wp_verify_nonce( $_POST['delete_nonce'], 'delete_advert' ) ) {
			$this->handle_advert_deletion();
		}

		// Enqueue media scripts
		wp_enqueue_media();

		$adverts = $this->get_parsed_adverts();
		?>
		<div class="wrap">
			<h1>House Adverts</h1>
			<p>Legacy adverts data parsed from options table for house landing pages placeholder system.</p>
			
			<!-- Upload Form -->
			<div style="background: #fff; padding: 20px; margin: 20px 0; border: 1px solid #ccd0d4;">
				<h2>Add New Advert</h2>
				<form method="post" action="">
					<?php wp_nonce_field( 'upload_advert', 'advert_nonce' ); ?>
					<table class="form-table">
						<tr>
							<th scope="row">Location</th>
							<td>
								<select name="advert_location" required>
									<option value="">Select Location</option>
									<option value="cotswolds">Cotswolds</option>
									<option value="sea">Coast</option>
									<option value="country">Country</option>
									<option value="town">Town</option>
								</select>
							</td>
						</tr>
						<tr>
							<th scope="row">Image</th>
							<td>
								<button type="button" id="upload-advert-image" class="button">Select Image</button>
								<input type="hidden" id="advert-attachment-id" name="attachment_id" required>
								<div id="advert-image-preview" style="margin-top: 10px;"></div>
							</td>
						</tr>
					</table>
					<?php submit_button( 'Add Advert', 'primary', 'upload_advert' ); ?>
				</form>
			</div>
			
			<div class="nav-tab-wrapper">
				<a href="#cotswolds" class="nav-tab nav-tab-active">Cotswolds</a>
				<a href="#sea" class="nav-tab">Coast</a>
				<a href="#country" class="nav-tab">Country</a>
				<a href="#town" class="nav-tab">Town</a>
			</div>

			<?php 
			$locations = array( 
				'cotswolds' => 'Cotswolds', 
				'sea' => 'Coast', 
				'country' => 'Country', 
				'town' => 'Town' 
			);
			foreach ( $locations as $location => $label ) : 
			?>
				<div id="<?php echo esc_attr( $location ); ?>" class="tab-content" <?php echo $location !== 'cotswolds' ? 'style="display:none;"' : ''; ?>>
					<h2><?php echo esc_html( $label ); ?> Adverts</h2>
					<?php if ( isset( $adverts[ $location ] ) && ! empty( $adverts[ $location ] ) ) : ?>
						<div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 20px; margin-top: 20px;">
							<?php foreach ( $adverts[ $location ] as $index => $advert ) : ?>
								<div style="border: 1px solid #ccd0d4; padding: 15px; background: #fff; position: relative;">
									<?php if ( $advert['image_url'] ) : ?>
										<img src="<?php echo esc_url( $advert['image_url'] ); ?>" 
										     style="width: 100%; height: 200px; object-fit: cover; margin-bottom: 10px;" 
										     alt="Advert <?php echo esc_attr( $advert['attachment_id'] ); ?>">
									<?php endif; ?>
									<p><strong>ID:</strong> <?php echo esc_html( $advert['attachment_id'] ); ?></p>
									<p><strong>Location:</strong> <?php echo esc_html( $advert['location'] ); ?></p>
									
									<!-- Delete Button -->
									<form method="post" style="margin-top: 10px;" onsubmit="return confirm('Are you sure you want to delete this advert?');">
										<?php wp_nonce_field( 'delete_advert', 'delete_nonce' ); ?>
										<input type="hidden" name="delete_attachment_id" value="<?php echo esc_attr( $advert['attachment_id'] ); ?>">
										<input type="hidden" name="delete_location" value="<?php echo esc_attr( $advert['location'] ); ?>">
										<button type="submit" name="delete_advert" class="button button-secondary button-small" style="color: #d63638;">Delete</button>
									</form>
								</div>
							<?php endforeach; ?>
						</div>
					<?php else : ?>
						<p>No adverts found for <?php echo esc_html( $label ); ?>.</p>
					<?php endif; ?>
				</div>
			<?php endforeach; ?>

			<script>
				jQuery(document).ready(function($) {
					// Tab switching
					$('.nav-tab').click(function(e) {
						e.preventDefault();
						$('.nav-tab').removeClass('nav-tab-active');
						$(this).addClass('nav-tab-active');
						$('.tab-content').hide();
						$($(this).attr('href')).show();
					});

					// Media library integration
					var mediaUploader;
					
					$('#upload-advert-image').click(function(e) {
						e.preventDefault();
						
						if (mediaUploader) {
							mediaUploader.open();
							return;
						}
						
						mediaUploader = wp.media({
							title: 'Select Advert Image',
							button: {
								text: 'Use This Image'
							},
							multiple: false,
							library: {
								type: 'image'
							}
						});
						
						mediaUploader.on('select', function() {
							var attachment = mediaUploader.state().get('selection').first().toJSON();
							$('#advert-attachment-id').val(attachment.id);
							$('#advert-image-preview').html(
								'<img src="' + attachment.url + '" style="max-width: 200px; height: auto;" alt="Selected image">' +
								'<p><strong>Selected:</strong> ' + attachment.filename + ' (ID: ' + attachment.id + ')</p>'
							);
						});
						
						mediaUploader.open();
					});
				});
			</script>
		</div>
		<?php
	}

	/**
	 * Parse legacy ACF adverts data
	 * 
	 * @return array Parsed adverts organized by location
	 */
	public function get_parsed_adverts() {
		$count = get_option( 'options_adverts', 0 );
		$adverts = array(
			'cotswolds' => array(),
			'sea' => array(), // Database uses 'sea' not 'coast'
			'country' => array(),
			'town' => array()
		);

		for ( $i = 0; $i < $count; $i++ ) {
			$image_id = get_option( "options_adverts_{$i}_advert_image" );
			$location = get_option( "options_adverts_{$i}_location" );

			if ( $image_id && $location && isset( $adverts[ $location ] ) ) {
				$image_url = wp_get_attachment_image_url( $image_id, 'full' );
				
				// Apply URL replacement for staging/production
				if ( $image_url ) {
					$image_url = $this->replace_image_urls( $image_url );
				}

				$adverts[ $location ][] = array(
					'attachment_id' => $image_id,
					'location' => $location,
					'image_url' => $image_url
				);
			}
		}

		return $adverts;
	}

	/**
	 * Replace URLs for staging/production domains
	 * 
	 * @param string $url The URL to process
	 * @return string The processed URL
	 */
	public function replace_image_urls( $url ) {
		$replacements = array(
			'https://kateandtomsblocks.test' => 'https://kateandtoms.com',
			'https://kateandtoms.test' => 'https://kateandtoms.com',
			'https://bigholidayhomes.co.uk' => 'https://kateandtoms.com'
		);

		foreach ( $replacements as $old => $new ) {
			$url = str_replace( $old, $new, $url );
		}

		return $url;
	}

	/**
	 * Get adverts for specific location (used by render.php)
	 * 
	 * @param string $location Location to get adverts for (pattern style)
	 * @param int $limit Maximum number of adverts to return
	 * @return array Array of advert data
	 */
	public function get_adverts_for_location( $location, $limit = 10 ) {
		// Map pattern styles to database location values
		$location_map = array(
			'coast' => 'sea', // coast pattern style maps to 'sea' in database
			'cotswolds' => 'cotswolds',
			'country' => 'country',
			'town' => 'town'
		);
		
		// Get the actual database location key
		$db_location = isset( $location_map[ $location ] ) ? $location_map[ $location ] : $location;
		
		$all_adverts = $this->get_parsed_adverts();
		
		if ( ! isset( $all_adverts[ $db_location ] ) ) {
			return array();
		}

		return array_slice( $all_adverts[ $db_location ], 0, $limit );
	}

	/**
	 * Get adverts pooled across every location (location-agnostic).
	 *
	 * Flattens get_parsed_adverts() into a single list. Used by the Special
	 * Offers Grid to fill incomplete rows with adverts regardless of location.
	 *
	 * @param int $limit Optional maximum number of adverts to return; 0 for all.
	 * @return array Array of advert data spanning all locations.
	 */
	public function get_all_adverts( $limit = 0 ) {
		$pool = array();

		foreach ( $this->get_parsed_adverts() as $location_adverts ) {
			foreach ( $location_adverts as $advert ) {
				$pool[] = $advert;
			}
		}

		if ( $limit > 0 && count( $pool ) > $limit ) {
			$pool = array_slice( $pool, 0, $limit );
		}

		return $pool;
	}

	/**
	 * Handle advert upload
	 */
	private function handle_advert_upload() {
		// Security check
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'You do not have sufficient permissions.' ) );
		}

		// Sanitize input
		$location = sanitize_key( $_POST['advert_location'] );
		$attachment_id = absint( $_POST['attachment_id'] );

		// Validate input
		if ( empty( $location ) || empty( $attachment_id ) ) {
			add_action( 'admin_notices', function() {
				echo '<div class="notice notice-error"><p>Please select both location and image.</p></div>';
			});
			return;
		}

		if ( ! in_array( $location, array( 'cotswolds', 'sea', 'country', 'town' ) ) ) {
			add_action( 'admin_notices', function() {
				echo '<div class="notice notice-error"><p>Invalid location selected.</p></div>';
			});
			return;
		}

		// Verify attachment exists
		if ( ! wp_attachment_is_image( $attachment_id ) ) {
			add_action( 'admin_notices', function() {
				echo '<div class="notice notice-error"><p>Invalid image attachment.</p></div>';
			});
			return;
		}

		// Get current count and increment
		$current_count = get_option( 'options_adverts', 0 );
		$new_index = $current_count;

		// Add new advert
		update_option( "options_adverts_{$new_index}_advert_image", $attachment_id );
		update_option( "options_adverts_{$new_index}_location", $location );
		
		// Update the count
		update_option( 'options_adverts', $current_count + 1 );

		// Success message
		add_action( 'admin_notices', function() use ( $location ) {
			echo '<div class="notice notice-success"><p>Advert added successfully to ' . ucfirst( $location ) . '!</p></div>';
		});
	}

	/**
	 * Handle advert deletion
	 */
	private function handle_advert_deletion() {
		// Security check
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'You do not have sufficient permissions.' ) );
		}

		// Sanitize input
		$attachment_id = absint( $_POST['delete_attachment_id'] );
		$location = sanitize_key( $_POST['delete_location'] );

		if ( empty( $attachment_id ) || empty( $location ) ) {
			add_action( 'admin_notices', function() {
				echo '<div class="notice notice-error"><p>Invalid advert data for deletion.</p></div>';
			});
			return;
		}

		// Find and remove the advert
		$current_count = get_option( 'options_adverts', 0 );
		$found = false;
		$deleted_index = -1;

		// Find the advert to delete
		for ( $i = 0; $i < $current_count; $i++ ) {
			$stored_image_id = get_option( "options_adverts_{$i}_advert_image" );
			$stored_location = get_option( "options_adverts_{$i}_location" );
			
			if ( $stored_image_id == $attachment_id && $stored_location == $location ) {
				$found = true;
				$deleted_index = $i;
				break;
			}
		}

		if ( ! $found ) {
			add_action( 'admin_notices', function() {
				echo '<div class="notice notice-error"><p>Advert not found for deletion.</p></div>';
			});
			return;
		}

		// Shift all subsequent entries down by 1
		for ( $i = $deleted_index; $i < $current_count - 1; $i++ ) {
			$next_image = get_option( "options_adverts_" . ( $i + 1 ) . "_advert_image" );
			$next_location = get_option( "options_adverts_" . ( $i + 1 ) . "_location" );
			
			update_option( "options_adverts_{$i}_advert_image", $next_image );
			update_option( "options_adverts_{$i}_location", $next_location );
		}

		// Delete the last entry
		delete_option( "options_adverts_" . ( $current_count - 1 ) . "_advert_image" );
		delete_option( "options_adverts_" . ( $current_count - 1 ) . "_location" );

		// Update the count
		update_option( 'options_adverts', $current_count - 1 );

		// Success message
		add_action( 'admin_notices', function() {
			echo '<div class="notice notice-success"><p>Advert deleted successfully!</p></div>';
		});
	}
}
