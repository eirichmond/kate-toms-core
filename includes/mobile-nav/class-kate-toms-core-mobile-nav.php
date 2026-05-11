<?php
/**
 * Mobile Nav Drilldown enhancement.
 *
 * Registers and conditionally enqueues the view script module + stylesheet
 * that upgrade the core/navigation block's mobile overlay into an iOS-style
 * drilldown below 1100px. The enhancement is applied entirely client-side
 * by extending the core/navigation Interactivity store, so no markup
 * changes happen in PHP.
 *
 * @package    Kate_Toms_Core
 * @subpackage Kate_Toms_Core/includes/mobile-nav
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Kate_Toms_Core_Mobile_Nav class.
 *
 * Wires the mobile-nav-drilldown view module and stylesheet to the
 * core/navigation block via init + render_block_core/navigation hooks.
 */
class Kate_Toms_Core_Mobile_Nav {

	/**
	 * Script module handle for the view module.
	 *
	 * @var string
	 */
	const SCRIPT_MODULE_HANDLE = 'kate-toms-core/mobile-nav-drilldown';

	/**
	 * Stylesheet handle for the drilldown stylesheet.
	 *
	 * @var string
	 */
	const STYLE_HANDLE = 'kate-toms-core-mobile-nav-drilldown';

	/**
	 * Register the script module and stylesheet with WordPress.
	 *
	 * Runs on the `init` action. The assets are registered here but only
	 * actually enqueued on pages that render a core/navigation block —
	 * see enqueue_on_navigation(), wired via render_block_core/navigation.
	 *
	 * @return void
	 */
	public function register_assets() {
		$asset_file = plugin_dir_path( KATE_TOMS_CORE_PLUGIN_FILE )
			. 'build/mobile-nav-drilldown/view.asset.php';
		$asset      = file_exists( $asset_file ) ? require $asset_file : array();
		$version    = isset( $asset['version'] ) ? $asset['version'] : KATE_TOMS_CORE_VERSION;

		if ( function_exists( 'wp_register_script_module' ) ) {
			wp_register_script_module(
				self::SCRIPT_MODULE_HANDLE,
				plugins_url( 'build/mobile-nav-drilldown/view.js', KATE_TOMS_CORE_PLUGIN_FILE ),
				array(),
				$version
			);
		}

		wp_register_style(
			self::STYLE_HANDLE,
			plugins_url( 'build/mobile-nav-drilldown/style-view.css', KATE_TOMS_CORE_PLUGIN_FILE ),
			array(),
			$version
		);

		add_filter(
			'script_module_data_' . self::SCRIPT_MODULE_HANDLE,
			array( $this, 'filter_script_module_data' )
		);
	}

	/**
	 * Provide runtime data to the view script module.
	 *
	 * WordPress renders the returned array as a JSON blob inside a
	 * `<script type="application/json" id="wp-script-module-data-{id}">`
	 * tag, which the view module reads at startup. Used to hand the
	 * view module the arrow icon URL without hard-coding the plugin path
	 * in JavaScript.
	 *
	 * @param array $data Incoming data (unused — we replace it).
	 * @return array Data exposed to the view module.
	 */
	public function filter_script_module_data( $data ) {
		unset( $data );

		return array(
			'arrowSrc' => plugins_url(
				'public/assets/images/right-arrow.png',
				KATE_TOMS_CORE_PLUGIN_FILE
			),
		);
	}

	/**
	 * Enqueue the drilldown assets when a core/navigation block renders.
	 *
	 * Wired to the `render_block_core/navigation` filter. Uses a static flag
	 * so the module and stylesheet are only enqueued once per request, no
	 * matter how many navigation blocks the page renders. This filter is a
	 * pass-through — it never modifies the block HTML.
	 *
	 * @param string $block_content The block HTML, returned unchanged.
	 * @return string The unchanged block HTML.
	 */
	public function enqueue_on_navigation( $block_content ) {
		static $enqueued = false;

		if ( $enqueued ) {
			return $block_content;
		}

		$enqueued = true;

		if ( function_exists( 'wp_enqueue_script_module' ) ) {
			wp_enqueue_script_module( self::SCRIPT_MODULE_HANDLE );
		}

		wp_enqueue_style( self::STYLE_HANDLE );

		return $block_content;
	}
}
