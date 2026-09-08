<?php
/**
 * Tests for parsing the Trustpilot business unit payload.
 *
 * @package kate-toms-core
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * @covers Kate_Toms_Trustpilot_Rating
 */
final class TrustpilotRatingTest extends TestCase {

	/**
	 * A payload shaped like the live API response.
	 *
	 * @param array $overrides Values to merge over the defaults.
	 * @return array
	 */
	private function payload( array $overrides = array() ): array {
		return array_replace_recursive(
			array(
				'displayName'     => "kate & tom's",
				'score'           => array(
					'stars'      => 5,
					'trustScore' => 4.9,
				),
				'numberOfReviews' => array(
					'total'                        => 976,
					'usedForTrustScoreCalculation' => 966,
				),
			),
			$overrides
		);
	}

	public function test_reads_the_live_response_shape(): void {
		$this->assertSame(
			array(
				'ratingValue' => 4.9,
				'ratingCount' => 976,
			),
			Kate_Toms_Trustpilot_Rating::from_payload( $this->payload() )
		);
	}

	/**
	 * `stars` is rounded to the nearest half — the API reports 5 against a
	 * TrustScore of 4.9. Publishing the rounded figure while the widget shows
	 * 4.9 is the mismatch Google's policy prohibits.
	 */
	public function test_uses_trustscore_not_the_rounded_stars(): void {
		$rating = Kate_Toms_Trustpilot_Rating::from_payload( $this->payload() );

		$this->assertSame( 4.9, $rating['ratingValue'] );
		$this->assertNotSame( 5.0, $rating['ratingValue'] );
	}

	/**
	 * The widget prints the total, so the markup must use the total.
	 */
	public function test_uses_total_reviews_not_the_scored_subset(): void {
		$rating = Kate_Toms_Trustpilot_Rating::from_payload( $this->payload() );

		$this->assertSame( 976, $rating['ratingCount'] );
	}

	public function test_values_are_numbers_not_strings(): void {
		$rating = Kate_Toms_Trustpilot_Rating::from_payload(
			$this->payload(
				array(
					'score'           => array( 'trustScore' => '4.7' ),
					'numberOfReviews' => array( 'total' => '312' ),
				)
			)
		);

		$this->assertIsFloat( $rating['ratingValue'] );
		$this->assertIsInt( $rating['ratingCount'] );
	}

	/**
	 * @dataProvider provide_unusable_payloads
	 *
	 * Nothing is published rather than something wrong.
	 *
	 * @param mixed $payload A payload that carries no usable score.
	 */
	public function test_unusable_payloads_publish_nothing( $payload ): void {
		$this->assertNull( Kate_Toms_Trustpilot_Rating::from_payload( $payload ) );
	}

	/**
	 * Payloads that must not produce a rating.
	 *
	 * @return array[]
	 */
	public static function provide_unusable_payloads(): array {
		return array(
			'not an array'      => array( 'nope' ),
			'null'              => array( null ),
			'empty'             => array( array() ),
			'no score'          => array( array( 'numberOfReviews' => array( 'total' => 976 ) ) ),
			'no count'          => array( array( 'score' => array( 'trustScore' => 4.9 ) ) ),
			'zero reviews'      => array(
				array(
					'score'           => array( 'trustScore' => 4.9 ),
					'numberOfReviews' => array( 'total' => 0 ),
				),
			),
			'score off scale'   => array(
				array(
					'score'           => array( 'trustScore' => 9.9 ),
					'numberOfReviews' => array( 'total' => 976 ),
				),
			),
			'zero score'        => array(
				array(
					'score'           => array( 'trustScore' => 0 ),
					'numberOfReviews' => array( 'total' => 976 ),
				),
			),
			'non numeric score' => array(
				array(
					'score'           => array( 'trustScore' => 'excellent' ),
					'numberOfReviews' => array( 'total' => 976 ),
				),
			),
		);
	}
}
