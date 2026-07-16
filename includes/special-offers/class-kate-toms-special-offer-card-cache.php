<?php
/**
 * Rendered-card cache for the special offers grid.
 *
 * Each offer card is a full pattern render — the theme's house-card pattern is
 * included and run through do_blocks() once per card. The offers page carries
 * ten grids and several hundred cards, and that render was the bulk of the
 * page's time.
 *
 * A card's HTML depends only on the house, the offer text, the offer date and
 * the pattern file — not on who is viewing or when. So it is rendered once and
 * kept. The key folds in the house's post_modified and the pattern's mtime, so
 * editing a house or the pattern drops the affected cards without any manual
 * cache clearing.
 *
 * Adverts are deliberately NOT cached: the placeholder cards are supposed to
 * show a different random advert on every render, and caching them would
 * quietly turn "random" into "fixed until the cache expires".
 *
 * @package Kate_Toms_Core
 */

if ( ! class_exists( 'Kate_Toms_Special_Offer_Card_Cache' ) ) {

	/**
	 * Caches the rendered HTML of individual special offer house cards.
	 */
	class Kate_Toms_Special_Offer_Card_Cache {

		/**
		 * Transient holding the rendered cards.
		 *
		 * @var string
		 */
		public const TRANSIENT = 'kt_special_offer_cards';

		/**
		 * How long rendered cards are kept.
		 *
		 * Entries are self-invalidating via the key, so this is only a backstop
		 * against the map growing stale entries forever.
		 *
		 * @var int
		 */
		public const TTL = 7 * DAY_IN_SECONDS;

		/**
		 * Cards loaded for this request.
		 *
		 * @var array<string, string>|null
		 */
		private static $cards = null;

		/**
		 * Whether anything new was rendered this request.
		 *
		 * @var bool
		 */
		private static $dirty = false;

		/**
		 * Load the card map once per request.
		 *
		 * @return array<string, string> Card HTML keyed by card key.
		 */
		private static function load() {
			if ( null === self::$cards ) {
				$cards = get_transient( self::TRANSIENT );

				self::$cards = is_array( $cards ) ? $cards : array();
			}

			return self::$cards;
		}

		/**
		 * Build the key for one card.
		 *
		 * The house's post_modified is in the key, so editing a house — its
		 * title, image, description or sleeps — drops its cards on the next
		 * render, with no manual cache clearing.
		 *
		 * @param WP_Post $house        The house being rendered.
		 * @param string  $offer        Offer text.
		 * @param string  $offer_date   Offer date.
		 * @param string  $pattern_file Path to the card pattern.
		 * @return string Card key.
		 */
		public static function key( WP_Post $house, $offer, $offer_date, $pattern_file ) {
			$pattern_stamp = ( $pattern_file && file_exists( $pattern_file ) )
				? (string) filemtime( $pattern_file )
				: '0';

			return md5(
				implode(
					'|',
					array(
						(string) $house->ID,
						(string) $house->post_modified_gmt,
						(string) $offer,
						(string) $offer_date,
						$pattern_stamp,
					)
				)
			);
		}

		/**
		 * Get a card's HTML, rendering and keeping it on a miss.
		 *
		 * @param string   $key      Card key from key().
		 * @param callable $renderer Renders the card and returns its HTML.
		 * @return string Card HTML.
		 */
		public static function remember( $key, callable $renderer ) {
			$cards = self::load();

			if ( isset( $cards[ $key ] ) ) {
				return $cards[ $key ];
			}

			$html = (string) call_user_func( $renderer );

			self::$cards[ $key ] = $html;
			self::$dirty         = true;

			return $html;
		}

		/**
		 * Persist newly rendered cards.
		 *
		 * Called once at the end of a render rather than per card, so a cold
		 * page costs one write instead of several hundred.
		 *
		 * @return void
		 */
		public static function persist() {
			if ( ! self::$dirty || null === self::$cards ) {
				return;
			}

			set_transient( self::TRANSIENT, self::$cards, self::TTL );

			self::$dirty = false;
		}
	}
}
