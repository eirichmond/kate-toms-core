<?php
/**
 * Precomputed house lists for the seasonal and availability landing pages.
 *
 * These pages ask "which houses still have a week / weekend / midweek free in
 * this date range", which cannot be expressed as a WP_Query: every house in the
 * region has to be checked against the booking calendar. Doing that during a
 * page request meant hundreds of calendar lookups and, on a cold cache, a live
 * HTTP call to the booking API per house — which is what made the pages hang
 * and time out.
 *
 * The answer only changes when the calendar changes, so it is computed once by
 * `wp kt-seasonal-cache warm` (nightly, after the calendar warm) and stored as
 * a plain list of house IDs. The page render is then a single transient read
 * and one WP_Query for the IDs.
 *
 * The list is keyed by what actually determines it — region, date range,
 * periods and ordering — not by the post, so two pages asking the same question
 * share one entry.
 *
 * @package Kate_Toms_Core
 */

if ( ! class_exists( 'Kate_Toms_Seasonal_Results_Cache' ) ) {

	/**
	 * Stores and reads the precomputed house ID lists.
	 */
	class Kate_Toms_Seasonal_Results_Cache {

		/**
		 * Transient key prefix.
		 *
		 * @var string
		 */
		public const PREFIX = 'kt_seasonal_ids_';

		/**
		 * How long a warmed list stays valid.
		 *
		 * Longer than the nightly warm interval on purpose: a slightly stale
		 * list is far better than falling back to the slow path.
		 *
		 * @var int
		 */
		public const TTL = 2 * DAY_IN_SECONDS;

		/**
		 * Build the cache key for one landing page section.
		 *
		 * @param array $criteria {
		 *     What determines the result set.
		 *
		 *     @type string $location  Location term slug for the region.
		 *     @type string $beginning Range start (Y-m-d).
		 *     @type string $ending    Range end (Y-m-d).
		 *     @type array  $periods   API period keys.
		 *     @type string $orderby   Query orderby.
		 *     @type string $order     Query order.
		 *     @type string $meta_key  Meta key used for ordering.
		 * }
		 * @return string Transient key.
		 */
		public static function key( array $criteria ) {
			$periods = isset( $criteria['periods'] ) ? (array) $criteria['periods'] : array();
			sort( $periods );

			$signature = wp_json_encode(
				array(
					'location'  => (string) ( $criteria['location'] ?? '' ),
					'beginning' => (string) ( $criteria['beginning'] ?? '' ),
					'ending'    => (string) ( $criteria['ending'] ?? '' ),
					'periods'   => $periods,
					'orderby'   => (string) ( $criteria['orderby'] ?? '' ),
					'order'     => (string) ( $criteria['order'] ?? '' ),
					'meta_key'  => (string) ( $criteria['meta_key'] ?? '' ),
				)
			);

			return self::PREFIX . md5( $signature );
		}

		/**
		 * Read a warmed house ID list.
		 *
		 * @param array $criteria Criteria, as for key().
		 * @return int[]|null Ordered house IDs, or null when not warmed.
		 */
		public static function get( array $criteria ) {
			$ids = get_transient( self::key( $criteria ) );

			return is_array( $ids ) ? array_map( 'intval', $ids ) : null;
		}

		/**
		 * Store a computed house ID list.
		 *
		 * @param array $criteria Criteria, as for key().
		 * @param int[] $house_ids Ordered house IDs.
		 * @return void
		 */
		public static function set( array $criteria, array $house_ids ) {
			set_transient(
				self::key( $criteria ),
				array_values( array_map( 'intval', $house_ids ) ),
				self::TTL
			);
		}
	}
}
