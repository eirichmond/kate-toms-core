<?php
/**
 * Booked-out detection for special offers.
 *
 * A special offer advertises a break starting on its offer date. Once that
 * break is booked, the offer is dead: the grid should stop advertising it and
 * the editor should flag it so staff can clear it out.
 *
 * Deciding that costs a calendar lookup per offer, and the offers page carries
 * ~370 of them, so the verdicts are precomputed into a single map by
 * `wp kt-offers-cache warm` (nightly, alongside the other cache warms) and the
 * page render is left with one transient read.
 *
 * The map is authoritative only where it says `true`. A missing or cold map
 * means "we do not know", and an offer we cannot judge is always shown — a
 * stale cache must never silently hide live offers.
 *
 * @package Kate_Toms_Core
 */

if ( ! class_exists( 'Kate_Toms_Special_Offer_Availability' ) ) {

	/**
	 * Computes, caches, and reads the booked-out verdicts for special offers.
	 */
	class Kate_Toms_Special_Offer_Availability {

		/**
		 * Transient holding the booked map.
		 *
		 * @var string
		 */
		public const TRANSIENT = 'kt_special_offer_booked_map';

		/**
		 * How long a warmed map stays valid.
		 *
		 * Deliberately longer than the nightly warm interval: a map that is a
		 * little stale is far better than no map, and the only cost of staleness
		 * is an offer lingering a few hours longer than it should.
		 *
		 * @var int
		 */
		public const TTL = 2 * DAY_IN_SECONDS;

		/**
		 * Weekdays a break can start on (ISO-8601: 1 = Monday, 5 = Friday).
		 *
		 * Mirrors the arrival-day rules in
		 * House_Calendar_Manager::get_booking_periods_for_date(): no break
		 * starts on a Thursday, Saturday or Sunday, so that method returns an
		 * empty period list for those days whether or not the house is free.
		 * Treating that as "booked" would wrongly hide healthy offers, so an
		 * offer dated on a non-arrival day is reported as unknown instead.
		 *
		 * @var int[]
		 */
		private const ARRIVAL_DAYS = array( 1, 2, 3, 5 );

		/**
		 * Build the cache key for one offer.
		 *
		 * @param int    $house_id   House post ID.
		 * @param string $offer_date Offer date as Y-m-d.
		 * @return string Map key.
		 */
		public static function key( $house_id, $offer_date ) {
			return (int) $house_id . '|' . substr( (string) $offer_date, 0, 10 );
		}

		/**
		 * Read the warmed booked map.
		 *
		 * @return array<string, bool> Map of offer key => is booked. Empty when cold.
		 */
		public static function get_booked_map() {
			$map = get_transient( self::TRANSIENT );

			return is_array( $map ) ? $map : array();
		}

		/**
		 * Whether a given offer is known to be booked out.
		 *
		 * Anything the map does not positively mark as booked is treated as
		 * bookable, so a cold or partial cache shows offers rather than hiding
		 * them.
		 *
		 * @param array<string, bool> $map        Booked map from get_booked_map().
		 * @param int                 $house_id   House post ID.
		 * @param string              $offer_date Offer date as Y-m-d.
		 * @return bool True only when the offer is known to be booked.
		 */
		public static function is_booked( array $map, $house_id, $offer_date ) {
			if ( empty( $offer_date ) ) {
				return false;
			}

			return true === ( $map[ self::key( $house_id, $offer_date ) ] ?? false );
		}

		/**
		 * Decide whether one offer is booked out.
		 *
		 * @param House_Calendar_Manager $manager    Calendar manager.
		 * @param int                    $house_id   House post ID.
		 * @param string                 $offer_date Offer date as Y-m-d.
		 * @return bool|null True if booked, false if bookable, null if undecidable.
		 */
		public static function is_offer_booked( $manager, $house_id, $offer_date ) {
			$property_id = $manager->get_property_id_from_wp_house_id( (int) $house_id );

			if ( ! $property_id ) {
				return null;
			}

			$checkin = DateTime::createFromFormat( 'Y-m-d', substr( (string) $offer_date, 0, 10 ) );

			if ( ! $checkin instanceof DateTime ) {
				return null;
			}

			// get_booking_periods_for_date() mutates a clone of this, so it must
			// stay a mutable DateTime rather than a DateTimeImmutable.
			$checkin->setTime( 0, 0, 0 );

			if ( ! in_array( (int) $checkin->format( 'N' ), self::ARRIVAL_DAYS, true ) ) {
				return null;
			}

			$periods = $manager->get_booking_periods_for_date( $property_id, $checkin );

			// No period can still start on the offer date: the break is gone.
			return empty( $periods );
		}

		/**
		 * Recompute the booked map for every offer currently on the site.
		 *
		 * @param callable|null $progress Optional callback, invoked per offer with
		 *                                ( $house_id, $offer_date, $verdict ).
		 * @return array{map: array<string, bool>, booked: int, bookable: int, unknown: int} Result summary.
		 */
		public static function build_booked_map( $progress = null ) {
			$manager  = new House_Calendar_Manager();
			$map      = array();
			$counts   = array(
				'booked'   => 0,
				'bookable' => 0,
				'unknown'  => 0,
			);
			$verdicts = array();

			foreach ( self::collect_offers() as $offer ) {
				$house_id   = $offer['house_id'];
				$offer_date = $offer['offer_date'];
				$cache_key  = self::key( $house_id, $offer_date );

				// The same house/date pair can be offered more than once; judge it
				// once, but still report progress for every offer seen.
				if ( ! array_key_exists( $cache_key, $verdicts ) ) {
					$verdict                = self::is_offer_booked( $manager, $house_id, $offer_date );
					$verdicts[ $cache_key ] = $verdict;

					if ( true === $verdict ) {
						$map[ $cache_key ] = true;
						++$counts['booked'];
					} elseif ( false === $verdict ) {
						$map[ $cache_key ] = false;
						++$counts['bookable'];
					} else {
						++$counts['unknown'];
					}
				}

				if ( is_callable( $progress ) ) {
					call_user_func( $progress, $house_id, $offer_date, $verdicts[ $cache_key ] );
				}
			}

			set_transient( self::TRANSIENT, $map, self::TTL );

			return array(
				'map'      => $map,
				'booked'   => $counts['booked'],
				'bookable' => $counts['bookable'],
				'unknown'  => $counts['unknown'],
			);
		}

		/**
		 * Collect every dated special offer on the site.
		 *
		 * @return array<int, array{house_id: int, offer_date: string}> Offers with a house and a date.
		 */
		public static function collect_offers() {
			global $wpdb;

			$post_ids = $wpdb->get_col(
				"SELECT ID FROM {$wpdb->posts}
				WHERE post_status = 'publish'
				AND post_content LIKE '%wp:kate-toms-core/special-offers-grid%'"
			);

			$offers = array();

			foreach ( $post_ids as $post_id ) {
				$post = get_post( (int) $post_id );

				if ( ! $post ) {
					continue;
				}

				foreach ( self::find_offer_blocks( parse_blocks( $post->post_content ) ) as $attrs ) {
					$house_id   = (int) ( $attrs['selectedPostId'] ?? 0 );
					$offer_date = substr( (string) ( $attrs['offerDate'] ?? '' ), 0, 10 );

					if ( $house_id > 0 && '' !== $offer_date ) {
						$offers[] = array(
							'house_id'   => $house_id,
							'offer_date' => $offer_date,
						);
					}
				}
			}

			return $offers;
		}

		/**
		 * Recursively pull the offer child blocks out of a parsed block tree.
		 *
		 * @param array $blocks Parsed blocks.
		 * @return array<int, array> Attribute arrays of each offer child block.
		 */
		private static function find_offer_blocks( array $blocks ) {
			$found = array();

			foreach ( $blocks as $block ) {
				if ( 'kate-toms-core/kateandtoms-special-offer-house' === ( $block['blockName'] ?? '' ) ) {
					$found[] = $block['attrs'] ?? array();
				}

				if ( ! empty( $block['innerBlocks'] ) ) {
					$found = array_merge( $found, self::find_offer_blocks( $block['innerBlocks'] ) );
				}
			}

			return $found;
		}
	}
}
