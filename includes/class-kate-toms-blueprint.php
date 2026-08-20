<?php
/**
 * House Blueprint onboarding feature.
 *
 * Registers the Blueprint admin submenu under the Houses CPT, exposes REST
 * endpoints for CRM search and page creation, and assembles draft posts from
 * the MASTER templates when staff onboard a new house.
 *
 * @package    Kate_Toms_Core
 * @subpackage Kate_Toms_Core/includes
 */

declare(strict_types=1);

/**
 * House Blueprint onboarding feature.
 *
 * Coordinates the wizard admin page, the REST API, and post creation. The
 * content of each page comes from Kate_Toms_Blueprint_Templates, its SEO
 * metadata from Kate_Toms_Blueprint_SEO, and the ongoing parent-to-child
 * relationships from Kate_Toms_Blueprint_Inheritance.
 */
class Kate_Toms_Blueprint {

	/**
	 * REST API namespace, matching the existing plugin convention.
	 *
	 * @var string
	 */
	private const REST_NAMESPACE = 'kate-toms/v1';

	/**
	 * Page content builder.
	 *
	 * @var Kate_Toms_Blueprint_Templates
	 */
	private Kate_Toms_Blueprint_Templates $templates;

	/**
	 * SEO metadata writer.
	 *
	 * @var Kate_Toms_Blueprint_SEO
	 */
	private Kate_Toms_Blueprint_SEO $seo;

	/**
	 * Registers WordPress hooks used by this feature.
	 */
	public function __construct() {
		$this->templates = new Kate_Toms_Blueprint_Templates();
		$this->seo       = new Kate_Toms_Blueprint_SEO();

		add_action( 'admin_menu', array( $this, 'register_admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
		add_action( 'init', array( $this, 'register_meta' ) );
	}

	/**
	 * Registers the Blueprint submenu page under the Houses CPT.
	 *
	 * @return void
	 */
	public function register_admin_menu(): void {
		add_submenu_page(
			'edit.php?post_type=houses',
			__( 'Blueprint', 'kate-toms-core' ),
			__( 'Blueprint', 'kate-toms-core' ),
			'manage_options',
			'house-blueprint',
			array( $this, 'render_admin_page' )
		);
	}

	/**
	 * Outputs the admin page shell that the React wizard mounts into.
	 *
	 * @return void
	 */
	public function render_admin_page(): void {
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'House Blueprint', 'kate-toms-core' ); ?></h1>
			<div id="kt-blueprint-root"></div>
		</div>
		<?php
	}

	/**
	 * Enqueues the React wizard assets, but only on the Blueprint admin page.
	 *
	 * @param string $hook Current admin page hook suffix.
	 *
	 * @return void
	 */
	public function enqueue_admin_assets( string $hook ): void {
		if ( 'houses_page_house-blueprint' !== $hook ) {
			return;
		}

		$plugin_dir = plugin_dir_path( __DIR__ );
		$asset_file = $plugin_dir . 'build/blueprint-admin/index.asset.php';

		if ( ! file_exists( $asset_file ) ) {
			return;
		}

		$asset = require $asset_file;

		wp_enqueue_script(
			'kt-blueprint-admin',
			plugins_url( 'build/blueprint-admin/index.js', $plugin_dir . 'kate-toms-core.php' ),
			$asset['dependencies'],
			$asset['version'],
			true
		);

		wp_enqueue_style( 'wp-components' );

		wp_localize_script(
			'kt-blueprint-admin',
			'ktBlueprintData',
			array(
				'restUrl'        => rest_url( self::REST_NAMESPACE ),
				'nonce'          => wp_create_nonce( 'wp_rest' ),
				'adminUrl'       => admin_url( 'post.php' ),
				'pages'          => $this->get_page_summary(),
				'missingSources' => $this->templates->get_missing_sources(),
			)
		);
	}

	/**
	 * Builds the page list the wizard renders on its review step.
	 *
	 * @return array<int, array{key: string, label: string, slug: string, source: string}> Page summary.
	 */
	private function get_page_summary(): array {
		$summary = array();

		foreach ( Kate_Toms_Blueprint_Templates::get_pages() as $key => $config ) {
			$summary[] = array(
				'key'    => $key,
				'label'  => $config['label'],
				'slug'   => 'parent' === $key ? '' : $key,
				'source' => 'wp_block' === $config['source']['type']
					? $config['source']['title']
					: __( 'Plugin template', 'kate-toms-core' ),
			);
		}

		return $summary;
	}

	/**
	 * Registers the Blueprint REST API routes.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			self::REST_NAMESPACE,
			'/blueprint/crm-search',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'handle_crm_search' ),
				'permission_callback' => array( $this, 'check_manage_options' ),
				'args'                => array(
					'query'   => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
						'minLength'         => 2,
					),
					'refresh' => array(
						'required' => false,
						'type'     => 'boolean',
						'default'  => false,
					),
				),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/blueprint/create',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'handle_create_blueprint' ),
				'permission_callback' => array( $this, 'check_manage_options' ),
				'args'                => array(
					'crm_id'        => array(
						'required' => true,
						'type'     => 'integer',
					),
					'display_title' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
						'maxLength'         => 200,
					),
					'force'         => array(
						'required' => false,
						'type'     => 'boolean',
						'default'  => false,
					),
				),
			)
		);
	}

	/**
	 * Registers the ipro_property_id post meta field on the houses post type.
	 *
	 * Stores the iPro PropertyId (the value the booking enquiry API's
	 * `&propertyids=` parameter needs). Exposed via REST so the block editor
	 * and API consumers can read it.
	 *
	 * @return void
	 */
	public function register_meta(): void {
		register_post_meta(
			'houses',
			'ipro_property_id',
			array(
				'type'          => 'integer',
				'single'        => true,
				'show_in_rest'  => true,
				'auth_callback' => static function (): bool {
					return current_user_can( 'manage_options' );
				},
			)
		);
	}

	/**
	 * Permission callback: requires manage_options capability.
	 *
	 * @return bool True if the current user can manage options.
	 */
	public function check_manage_options(): bool {
		return current_user_can( 'manage_options' );
	}

	/**
	 * REST callback: searches the CRM for houses matching a query string.
	 *
	 * @param WP_REST_Request $request The incoming REST request.
	 *
	 * @return WP_REST_Response|WP_Error Matching houses or an error.
	 */
	public function handle_crm_search( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$query   = (string) $request->get_param( 'query' );
		$refresh = (bool) $request->get_param( 'refresh' );

		$results = ( new Kate_Toms_Blueprint_CRM_API() )->search_houses( $query, $refresh );

		return new WP_REST_Response( $results, 200 );
	}

	/**
	 * REST callback: creates the parent Houses post and all child posts.
	 *
	 * @param WP_REST_Request $request The incoming REST request.
	 *
	 * @return WP_REST_Response|WP_Error Created post data or an error.
	 */
	public function handle_create_blueprint( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$crm_id        = (int) $request->get_param( 'crm_id' );
		$display_title = (string) $request->get_param( 'display_title' );
		$force         = (bool) $request->get_param( 'force' );

		if ( ! $force ) {
			$existing_id = $this->house_title_exists( $display_title );

			if ( null !== $existing_id ) {
				return new WP_Error(
					'kt_blueprint_duplicate',
					__( 'A house with this title already exists.', 'kate-toms-core' ),
					array(
						'status'           => 409,
						'existing_post_id' => $existing_id,
						'existing_title'   => get_the_title( $existing_id ),
					)
				);
			}
		}

		return $this->create_blueprint_posts( $crm_id, $display_title );
	}

	/**
	 * Checks whether a Houses post with the given title already exists.
	 *
	 * @param string $title Post title to check (exact match, any status).
	 *
	 * @return int|null Matching post ID, or null if none found.
	 */
	private function house_title_exists( string $title ): ?int {
		$query = new WP_Query(
			array(
				'post_type'              => 'houses',
				'post_status'            => 'any',
				'title'                  => $title,
				'posts_per_page'         => 1,
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);

		$posts = $query->posts;

		return ! empty( $posts ) ? (int) $posts[0] : null;
	}

	/**
	 * Creates the parent Houses post and all child posts for a blueprint run.
	 *
	 * The parent is inserted empty first so WordPress can settle its final slug
	 * — that slug is woven through every page's navigation links and heading
	 * anchors, so the content is only generated once it is known.
	 *
	 * @param int    $crm_id        CRM property ID to store as post meta.
	 * @param string $display_title Display title for the parent post.
	 *
	 * @return WP_REST_Response|WP_Error Response with created post data, or error.
	 */
	private function create_blueprint_posts( int $crm_id, string $display_title ): WP_REST_Response|WP_Error {
		$parent_id = wp_insert_post(
			array(
				'post_title'  => $display_title,
				'post_name'   => sanitize_title( $display_title ),
				'post_type'   => 'houses',
				'post_status' => 'draft',
			),
			true
		);

		if ( is_wp_error( $parent_id ) ) {
			return $parent_id;
		}

		$house_slug = (string) get_post_field( 'post_name', $parent_id );
		$parent_url = Kate_Toms_Blueprint_SEO::build_parent_url( $house_slug );

		update_post_meta( $parent_id, 'ipro_property_id', $crm_id );

		wp_update_post(
			array(
				'ID'           => $parent_id,
				'post_content' => $this->templates->get_content( 'parent', $display_title, $house_slug, $crm_id ),
			)
		);

		$this->seo->apply( $parent_id, 'parent', $display_title, $parent_url );

		$created    = array( $this->format_post_result( 'parent', $parent_id, $display_title ) );
		$menu_order = 0;

		foreach ( Kate_Toms_Blueprint_Templates::get_child_keys() as $key ) {
			++$menu_order;

			$title    = Kate_Toms_Blueprint_Templates::build_title( $display_title, $key );
			$child_id = wp_insert_post(
				array(
					'post_title'   => $title,
					'post_name'    => $key,
					'post_type'    => 'houses',
					'post_status'  => 'draft',
					'post_parent'  => $parent_id,
					'menu_order'   => $menu_order,
					'post_content' => $this->templates->get_content( $key, $display_title, $house_slug, $crm_id ),
				),
				true
			);

			if ( is_wp_error( $child_id ) ) {
				return $child_id;
			}

			$this->seo->apply( $child_id, $key, $display_title, $parent_url );

			$created[] = $this->format_post_result( $key, $child_id, $title );
		}

		return new WP_REST_Response( $created, 201 );
	}

	/**
	 * Formats a created post into the REST response shape.
	 *
	 * @param string $page_key Page identifier key.
	 * @param int    $post_id  WordPress post ID.
	 * @param string $title    Post title.
	 *
	 * @return array{ page_key: string, post_id: int, edit_url: string, title: string }
	 */
	private function format_post_result( string $page_key, int $post_id, string $title ): array {
		return array(
			'page_key' => $page_key,
			'post_id'  => $post_id,
			'edit_url' => get_edit_post_link( $post_id, 'raw' ),
			'title'    => $title,
		);
	}
}
