<?php
/**
 * Special Offers Grid ordering logic.
 *
 * Pure, framework-free helper that turns the raw attributes of the
 * special-offer-house child blocks into an ordered, filtered render list for
 * the Special Offers Grid parent block. Deliberately contains NO WordPress
 * calls and reads NO globals so it can be unit-tested without wp-env.
 *
 * @package kate-toms-core
 */

declare(strict_types=1);

/**
 * Orders and filters special-offer-house cards for front-end rendering.
 */
class Kate_Toms_Special_Offers_Grid {

	/**
	 * Card type for a selected house.
	 *
	 * @var string
	 */
	public const TYPE_HOUSE = 'house';

	/**
	 * Card type for a manual random-placeholder advert.
	 *
	 * @var string
	 */
	public const TYPE_PLACEHOLDER = 'placeholder';

	/**
	 * Number of cards per full row on the widest layout.
	 *
	 * @var int
	 */
	public const CARDS_PER_ROW = 4;

	/**
	 * Minimum lead time, in days, between today and an offer's expiry date.
	 *
	 * An offer is pulled from the grid once it is this close to expiring, so
	 * guests are not shown breaks they realistically can no longer book. The
	 * legacy site used five days; the business asked for three.
	 *
	 * @var int
	 */
	public const MIN_LEAD_DAYS = 3;

	/**
	 * Builds the filtered render list from raw child attributes.
	 *
	 * Cards keep the order the editor placed them in: editors group offers by
	 * stay type (weekends, then weeks, then midweeks) and order within each
	 * group by hand, so the front end must not re-sort them (#493). It used to
	 * sort dated cards by expiry, which scattered each group across the grid.
	 *
	 * House cards are dropped once their offer is within MIN_LEAD_DAYS of its
	 * expiry date (site timezone) — so an offer expiring today, in the past, or
	 * in the next three days is not rendered. Dateless house cards and manual
	 * placeholder cards are never date-filtered and stay where they were placed.
	 *
	 * @param array<int, array<string, mixed>> $children Raw attribute arrays, one per child block, in editor order.
	 * @param \DateTimeImmutable               $now      Current moment in the site timezone (date component used for expiry).
	 *
	 * @return array<int, array<string, mixed>> Filtered card arrays, in editor order, ready for rendering.
	 */
	public static function order_cards( array $children, \DateTimeImmutable $now ): array {
		$cutoff = self::expiry_cutoff( $now );
		$cards  = array();

		foreach ( $children as $child ) {
			$card = self::normalize_card( $child );

			// Offers at or inside the lead-time cutoff are dropped; placeholders and dateless houses never are.
			if ( self::TYPE_HOUSE === $card['type'] && null !== $card['offerDateNormalized'] && $card['offerDateNormalized'] <= $cutoff ) {
				continue;
			}

			$cards[] = $card;
		}

		return $cards;
	}

	/**
	 * The latest offer date that is still too close to expiry to be shown.
	 *
	 * An offer is rendered only when its date is strictly after this cutoff, so
	 * an offer expiring in exactly MIN_LEAD_DAYS days is dropped and one
	 * expiring a day later is kept.
	 *
	 * @param \DateTimeImmutable $now Current moment in the site timezone.
	 *
	 * @return string Cutoff date as Y-m-d.
	 */
	public static function expiry_cutoff( \DateTimeImmutable $now ): string {
		return $now->modify( '+' . self::MIN_LEAD_DAYS . ' days' )->format( 'Y-m-d' );
	}

	/**
	 * Counts the advert placeholders needed to complete the final row.
	 *
	 * Callers pass the number of cells actually rendered (house cards plus
	 * manual placeholders, excluding any skipped invalid houses). The result
	 * tops that up to the next multiple of $per_row so the last desktop row is
	 * never left short. An empty grid needs no adverts.
	 *
	 * @param int $card_count Number of rendered cells.
	 * @param int $per_row    Cards per full row (defaults to CARDS_PER_ROW).
	 *
	 * @return int Number of advert placeholders to append (0 when already full or empty).
	 */
	public static function advert_fill_count( int $card_count, int $per_row = self::CARDS_PER_ROW ): int {
		if ( $per_row < 1 || $card_count < 1 ) {
			return 0;
		}

		$remainder = $card_count % $per_row;

		return 0 === $remainder ? 0 : $per_row - $remainder;
	}

	/**
	 * Coerces one raw child attribute array into a consistent card shape.
	 *
	 * Applies the block.json defaults and classifies the card as a house or a
	 * manual placeholder so downstream logic never has to re-check raw keys.
	 *
	 * @param array<string, mixed> $child Raw child block attributes.
	 *
	 * @return array<string, mixed> Normalised card.
	 */
	private static function normalize_card( array $child ): array {
		$is_placeholder = ! empty( $child['isPlaceholder'] );
		$offer_date     = isset( $child['offerDate'] ) ? (string) $child['offerDate'] : '';

		return array(
			'type'                => $is_placeholder ? self::TYPE_PLACEHOLDER : self::TYPE_HOUSE,
			'selectedPostId'      => isset( $child['selectedPostId'] ) ? (int) $child['selectedPostId'] : 0,
			'offer'               => isset( $child['offer'] ) ? (string) $child['offer'] : '',
			'offerDate'           => $offer_date,
			'offerDateNormalized' => self::extract_date( $offer_date ),
			'isPlaceholder'       => $is_placeholder,
			'placeholderLocation' => isset( $child['placeholderLocation'] ) ? (string) $child['placeholderLocation'] : '',
		);
	}

	/**
	 * Extracts a validated Y-m-d calendar date from a stored offer-date string.
	 *
	 * The DatePicker stores a wall-clock date (optionally with a time/zone
	 * suffix, e.g. "2026-08-01T00:00:00"). Only the leading calendar date is
	 * used and no timezone conversion is applied, so the intended date is never
	 * shifted. Empty or malformed values yield null (treated as dateless).
	 *
	 * @param string $offer_date Raw offer-date attribute value.
	 *
	 * @return string|null Validated 'Y-m-d' date, or null when absent/invalid.
	 */
	private static function extract_date( string $offer_date ): ?string {
		$offer_date = trim( $offer_date );

		if ( '' === $offer_date ) {
			return null;
		}

		$candidate = substr( $offer_date, 0, 10 );
		$parsed    = \DateTimeImmutable::createFromFormat( '!Y-m-d', $candidate );

		// Reject impossible dates (e.g. 2026-13-40) by requiring a clean round-trip.
		if ( false === $parsed || $parsed->format( 'Y-m-d' ) !== $candidate ) {
			return null;
		}

		return $candidate;
	}
}
