<?php
/**
 * Reads a publishable rating out of a Trustpilot business unit payload.
 *
 * @package Kate_Toms_Core
 */

if ( ! class_exists( 'Kate_Toms_Trustpilot_Rating' ) ) {

	/**
	 * Turns Trustpilot's business unit response into aggregateRating figures.
	 *
	 * Pure logic — no WordPress dependency, so it can be unit tested directly.
	 */
	class Kate_Toms_Trustpilot_Rating {

		/**
		 * Pull the publishable figures out of an API payload.
		 *
		 * `score.trustScore` is used rather than `score.stars`: stars is rounded
		 * to the nearest half — the API reports 5 against a TrustScore of 4.9 —
		 * and publishing a rounded 5 while the widget displays 4.9 is exactly
		 * the mismatch Google's structured data policy prohibits.
		 *
		 * `numberOfReviews.total` is used rather than
		 * `usedForTrustScoreCalculation` because total is the figure the widget
		 * prints, and the markup has to agree with what a visitor can see.
		 *
		 * @param mixed $payload Decoded API response.
		 * @return array|null array{ratingValue: float, ratingCount: int}, or
		 *                    null when the payload carries no usable score.
		 */
		public static function from_payload( $payload ) {
			if ( ! is_array( $payload ) ) {
				return null;
			}

			$score = isset( $payload['score']['trustScore'] ) ? $payload['score']['trustScore'] : null;
			$count = isset( $payload['numberOfReviews']['total'] ) ? $payload['numberOfReviews']['total'] : null;

			if ( ! is_numeric( $score ) || ! is_numeric( $count ) ) {
				return null;
			}

			$score = (float) $score;
			$count = (int) $count;

			// A zero count means nothing has been reviewed, and a score outside
			// the scale means the payload is not what we think it is. Emitting
			// either would be worse than emitting nothing.
			if ( $count < 1 || $score <= 0 || $score > 5 ) {
				return null;
			}

			return array(
				'ratingValue' => round( $score, 1 ),
				'ratingCount' => $count,
			);
		}
	}
}
