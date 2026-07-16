<?php
/**
 * REST endpoint serving the special offers booked map to the block editor.
 *
 * The editor needs to know which offers are booked out so it can flag them for
 * staff. It reads the same precomputed map the front end uses, in one request
 * for the whole grid, rather than a calendar lookup per offer block.
 *
 * @package Kate_Toms_Core
 */

if ( ! class_exists( 'Kate_Toms_Special_Offer_Availability_API' ) ) {

	/**
	 * Exposes the booked map at kate-toms/v1/special-offers/booked.
	 */
	class Kate_Toms_Special_Offer_Availability_API {

		/**
		 * Hook the route registration.
		 */
		public function __construct() {
			add_action( 'rest_api_init', array( $this, 'register_routes' ) );
		}

		/**
		 * Register the booked-map route.
		 */
		public function register_routes() {
			register_rest_route(
				'kate-toms/v1',
				'/special-offers/booked',
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_booked_map' ),
					'permission_callback' => array( $this, 'can_edit_posts' ),
				)
			);
		}

		/**
		 * Only editors of the offers page need this.
		 *
		 * @return bool Whether the current user may read the map.
		 */
		public function can_edit_posts() {
			return current_user_can( 'edit_posts' );
		}

		/**
		 * Return the booked map, plus whether it has ever been warmed.
		 *
		 * `warmed` lets the editor tell "nothing is booked" apart from "we have
		 * not checked yet", so it can avoid implying every offer is healthy when
		 * the cache is simply cold.
		 *
		 * @return WP_REST_Response The booked map keyed by "houseId|Y-m-d".
		 */
		public function get_booked_map() {
			$map = Kate_Toms_Special_Offer_Availability::get_booked_map();

			return rest_ensure_response(
				array(
					'warmed' => ! empty( $map ),
					'booked' => array_keys( array_filter( $map ) ),
				)
			);
		}
	}
}
