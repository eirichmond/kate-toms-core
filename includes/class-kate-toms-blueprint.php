<?php
/**
 * House Blueprint onboarding feature.
 *
 * Registers the Blueprint admin submenu under the Houses CPT, exposes REST
 * endpoints for CRM search and page creation, and assembles draft posts with
 * pre-loaded block patterns when staff onboard a new house.
 *
 * @package    Kate_Toms_Core
 * @subpackage Kate_Toms_Core/includes
 */

declare(strict_types=1);

/**
 * House Blueprint onboarding feature.
 *
 * Coordinates the wizard admin page, REST API, and post-creation logic.
 * Pattern assignments per page are configured in $blueprint_pages — a
 * developer-maintained static array; add a slug there to include a new
 * pattern without touching the wizard UI.
 */
class Kate_Toms_Blueprint {

	/**
	 * REST API namespace, matching the existing plugin convention.
	 *
	 * @var string
	 */
	private const REST_NAMESPACE = 'kate-toms/v1';

	/**
	 * Page configuration: keys are page identifiers, values contain the ordered
	 * list of katomswold pattern slugs to insert into each page's post_content.
	 *
	 * The 'parent' entry populates the Houses CPT post itself. All other keys
	 * become child Houses posts with fixed slugs matching the key name.
	 *
	 * @var array<string, array{patterns: string[]}>
	 */
	private static array $blueprint_pages = array(
		'parent'       => array(
			'patterns' => array(
				'katomswold/house-title-banner',
				'katomswold/standard-widget-fourimage',
				'katomswold/wide-widget',
				'katomswold/houses-you-may-also-like',
			),
		),
		'more'         => array(
			'patterns' => array(
				'katomswold/house-title-banner-sub-page',
				'katomswold/standard-widget-galleryright',
				'katomswold/button-widget',
			),
		),
		'availability' => array(
			'patterns' => array(
				'katomswold/house-title-banner-sub-page',
				'katomswold/button-widget',
			),
		),
		'book'         => array(
			'patterns' => array(
				'katomswold/house-title-banner-sub-page',
				'katomswold/button-widget',
			),
		),
		'facts'        => array(
			'patterns' => array(
				'katomswold/house-title-banner-sub-page',
				'katomswold/standard-widget-fourimage',
				'katomswold/wide-widget',
			),
		),
		'gallery'      => array(
			'patterns' => array(
				'katomswold/house-title-banner-sub-page',
				'katomswold/standard-widget-galleryright',
				'katomswold/button-widget',
			),
		),
	);

	/**
	 * Registers WordPress hooks used by this feature.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
		add_action( 'init', array( $this, 'register_meta' ) );
	}

	/**
	 * Returns the blueprint page configuration array.
	 *
	 * Exposes the private static config for testing and tooling.
	 *
	 * @return array<string, array{patterns: string[]}> Blueprint page config.
	 */
	public static function get_blueprint_pages(): array {
		return self::$blueprint_pages;
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
				'restUrl'  => rest_url( self::REST_NAMESPACE ),
				'nonce'    => wp_create_nonce( 'wp_rest' ),
				'adminUrl' => admin_url( 'post.php' ),
				'pages'    => self::get_blueprint_pages(),
			)
		);
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
					'query' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
						'minLength'         => 2,
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
	 * Registers the crm_house_id post meta field on the houses post type.
	 *
	 * Exposed via REST so the block editor and API consumers can read it.
	 *
	 * @return void
	 */
	public function register_meta(): void {
		register_post_meta(
			'houses',
			'crm_house_id',
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
		$query = (string) $request->get_param( 'query' );

		$results = ( new Kate_Toms_Blueprint_CRM_API() )->search_houses( $query );

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
	 * Assembles block pattern content for a list of pattern slugs.
	 *
	 * Looks up each slug in WP_Block_Patterns_Registry and concatenates the
	 * content strings. Logs a warning and skips any slug not found.
	 *
	 * @param string[] $slugs Ordered array of pattern slugs to assemble.
	 *
	 * @return string Block markup ready for post_content.
	 */
	private function get_patterns_content( array $slugs ): string {
		$registry = WP_Block_Patterns_Registry::get_instance();
		$content  = '';

		foreach ( $slugs as $slug ) {
			$pattern = $registry->get_registered( $slug );

			if ( false === $pattern ) {
				$this->log_warning( "Pattern not found: {$slug}" );
				continue;
			}

			$content .= $pattern['content'];
		}

		return $content;
	}

	/**
	 * Creates the parent Houses post and all child posts for a blueprint run.
	 *
	 * @param int    $crm_id        CRM property ID to store as post meta.
	 * @param string $display_title Display title for the parent post.
	 *
	 * @return WP_REST_Response|WP_Error Response with created post data, or error.
	 */
	private function create_blueprint_posts( int $crm_id, string $display_title ): WP_REST_Response|WP_Error {
		$parent_id = $this->insert_parent_post( $display_title, $crm_id );

		if ( is_wp_error( $parent_id ) ) {
			return $parent_id;
		}

		$created = array(
			$this->format_post_result( 'parent', $parent_id, $display_title ),
		);

		foreach ( self::$blueprint_pages as $key => $config ) {
			if ( 'parent' === $key ) {
				continue;
			}

			$child_title = $this->build_child_title( $display_title, $key );
			$child_id    = $this->insert_child_post( $child_title, $key, $parent_id, $config['patterns'] );

			if ( is_wp_error( $child_id ) ) {
				return $child_id;
			}

			$created[] = $this->format_post_result( $key, $child_id, $child_title );
		}

		return new WP_REST_Response( $created, 201 );
	}

	/**
	 * Inserts the parent Houses draft post and saves CRM meta.
	 *
	 * @param string $title  Post title (display title).
	 * @param int    $crm_id CRM property ID to store as crm_house_id meta.
	 *
	 * @return int|WP_Error New post ID on success, WP_Error on failure.
	 */
	private function insert_parent_post( string $title, int $crm_id ): int|WP_Error {
		$post_id = wp_insert_post(
			array(
				'post_title'   => $title,
				'post_type'    => 'houses',
				'post_status'  => 'draft',
				'post_content' => $this->get_patterns_content( self::$blueprint_pages['parent']['patterns'] ),
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		update_post_meta( $post_id, 'crm_house_id', $crm_id );

		return $post_id;
	}

	/**
	 * Inserts a child Houses draft post under the parent.
	 *
	 * @param string   $title     Post title.
	 * @param string   $slug      Fixed post slug (e.g. 'availability').
	 * @param int      $parent_id Parent Houses post ID.
	 * @param string[] $patterns  Pattern slugs to assemble into post_content.
	 *
	 * @return int|WP_Error New post ID on success, WP_Error on failure.
	 */
	private function insert_child_post( string $title, string $slug, int $parent_id, array $patterns ): int|WP_Error {
		return wp_insert_post(
			array(
				'post_title'   => $title,
				'post_name'    => $slug,
				'post_type'    => 'houses',
				'post_status'  => 'draft',
				'post_parent'  => $parent_id,
				'post_content' => $this->get_patterns_content( $patterns ),
			),
			true
		);
	}

	/**
	 * Builds the title for a child post based on its page key.
	 *
	 * The 'more' page uses the display title alone. All others append
	 * ' - {key} - Kate and Tom's' to the display title.
	 *
	 * @param string $display_title The parent display title.
	 * @param string $key           Page key (e.g. 'availability', 'more').
	 *
	 * @return string Child post title.
	 */
	private function build_child_title( string $display_title, string $key ): string {
		if ( 'more' === $key ) {
			return $display_title;
		}

		return sprintf( "%s - %s - Kate and Tom's", $display_title, $key );
	}

	/**
	 * Formats a created post into the REST response shape.
	 *
	 * @param string $page_key  Page identifier key.
	 * @param int    $post_id   WordPress post ID.
	 * @param string $title     Post title.
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

	/**
	 * Writes a warning to the PHP error log when WP_DEBUG is active.
	 *
	 * @param string $message Warning message.
	 *
	 * @return void
	 */
	private function log_warning( string $message ): void {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( '[Kate & Toms Blueprint] ' . $message );
		}
	}
}
