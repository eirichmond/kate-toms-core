<?php
/**
 * Unit tests for KT_Cleanup_Key_Rules (BugHerd #423).
 *
 * Runs against the real retained-keys.php / legacy-keys.php so a bad edit to
 * either file fails here before it reaches a database.
 *
 * @package kate-toms-core
 */

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Covers key matching, LIKE escaping, the retained guard and the SQL pre-filter.
 */
final class CleanupKeyRulesTest extends TestCase {

	/**
	 * Rules built from the shipped key files.
	 *
	 * @var KT_Cleanup_Key_Rules
	 */
	private KT_Cleanup_Key_Rules $rules;

	/**
	 * Build the rules.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		$this->rules = KT_Cleanup_Key_Rules::from_files();
	}

	/**
	 * Legacy postmeta keys, including ACF `_` reference rows.
	 *
	 * @return array<string, array{string}>
	 */
	public static function legacy_postmeta_keys(): array {
		return array(
			'widgets count row'         => array( 'widgets' ),
			'widgets field'             => array( 'widgets_0_body' ),
			'widgets ACF ref'           => array( '_widgets_12_buttons_3_button_link' ),
			'widgets layout meta'       => array( '_widgets_layout_meta' ),
			'lower widgets'             => array( 'lower_widgets_4_faq_2_field_the_answer' ),
			'kf widgets floorplan'      => array( 'kf_widgets_0_floorplan_image' ),
			'top widgets'               => array( 'top_widgets_0_buttons' ),
			'availability rates'        => array( 'availability_calendar_3_rates_2_rate_11' ),
			'availability rate types'   => array( '_availability_calendar_0_rate_types_1_period' ),
			'price details extra'       => array( 'price_details_extra_7_details_period' ),
			'price details'             => array( 'price_details' ),
			'winter description'        => array( 'brief_description_winter' ),
			'legacy availability id'    => array( 'availability_site_post_id' ),
			'discount logic layout ref' => array( '_discount_logic_layout_meta' ),
		);
	}

	/**
	 * Retained keys that must never be deleted from a live post.
	 *
	 * @return array<string, array{string}>
	 */
	public static function retained_keys(): array {
		return array(
			'sleeps'                => array( 'sleeps_max' ),
			'sleeps ACF ref'        => array( '_sleeps_min' ),
			'location text'         => array( 'location_text' ),
			'brief description'     => array( 'brief_description' ),
			'house photos'          => array( 'house_photos' ),
			'house photos ref'      => array( '_house_photos' ),
			'ipro'                  => array( 'ipro_property_id' ),
			'signature'             => array( '_signature_collection_enabled' ),
			'calendar month'        => array( 'availability_calendar_5_month' ),
			'calendar days ref'     => array( '_availability_calendar_0_availability-days' ),
			'calendar count'        => array( 'availability_calendar' ),
			'vr backup'             => array( '_kt_vr_tour_backup' ),
			'advert image option'   => array( 'options_adverts_3_advert_image' ),
			'migration keyfacts'    => array( 'keyfacts_2' ),
			'migration location'    => array( 'location' ),
			'migration placeholder' => array( 'ah_from_size' ),
		);
	}

	/**
	 * Keys outside the cleanup entirely.
	 *
	 * @return array<string, array{string}>
	 */
	public static function out_of_scope_keys(): array {
		return array(
			'attachment metadata' => array( '_wp_attachment_metadata' ),
			'thumbnail'           => array( '_thumbnail_id' ),
			'yoast'               => array( '_yoast_wpseo_metadesc' ),
			'core footnotes'      => array( 'footnotes' ),
			'singular widget'     => array( 'widget_text' ),
			'widgetsomething'     => array( 'widgetsx' ),
			'media audit'         => array( '_media_audit' ),
		);
	}

	/**
	 * Legacy keys are deleted on live posts and revisions.
	 *
	 * @param string $key Meta key.
	 * @return void
	 */
	#[DataProvider( 'legacy_postmeta_keys' )]
	public function test_legacy_keys_are_deleted( string $key ): void {
		$this->assertTrue( $this->rules->is_legacy( 'postmeta', $key ) );
		$this->assertSame( KT_Cleanup_Key_Rules::DELETE, $this->rules->decide( 'postmeta', $key, KT_Cleanup_Key_Rules::SCOPE_LIVE ) );
		$this->assertSame( KT_Cleanup_Key_Rules::DELETE, $this->rules->decide( 'postmeta', $key, KT_Cleanup_Key_Rules::SCOPE_REVISION ) );
	}

	/**
	 * Legacy-key orphans are only reported.
	 *
	 * @return void
	 */
	public function test_legacy_orphans_are_reported_not_deleted(): void {
		$this->assertSame( KT_Cleanup_Key_Rules::ORPHAN, $this->rules->decide( 'postmeta', 'widgets_0_body', KT_Cleanup_Key_Rules::SCOPE_ORPHAN ) );
	}

	/**
	 * Retained keys are kept on live posts and deleted from revisions.
	 *
	 * @param string $key Meta key.
	 * @return void
	 */
	#[DataProvider( 'retained_keys' )]
	public function test_retained_keys_kept_live_deleted_on_revisions( string $key ): void {
		$this->assertTrue( $this->rules->is_protected( $key ) );
		$this->assertSame( KT_Cleanup_Key_Rules::KEEP, $this->rules->decide( 'postmeta', $key, KT_Cleanup_Key_Rules::SCOPE_LIVE ) );
		$this->assertSame( KT_Cleanup_Key_Rules::KEEP, $this->rules->decide( 'postmeta', $key, KT_Cleanup_Key_Rules::SCOPE_ORPHAN ) );
		$this->assertSame( KT_Cleanup_Key_Rules::DELETE, $this->rules->decide( 'postmeta', $key, KT_Cleanup_Key_Rules::SCOPE_REVISION ) );
	}

	/**
	 * Keys in neither list are never touched, in any scope.
	 *
	 * @param string $key Meta key.
	 * @return void
	 */
	#[DataProvider( 'out_of_scope_keys' )]
	public function test_out_of_scope_keys_are_ignored( string $key ): void {
		foreach ( array( KT_Cleanup_Key_Rules::SCOPE_LIVE, KT_Cleanup_Key_Rules::SCOPE_REVISION, KT_Cleanup_Key_Rules::SCOPE_ORPHAN ) as $scope ) {
			$this->assertNull( $this->rules->decide( 'postmeta', $key, $scope ) );
		}
	}

	/**
	 * Retained beats legacy when a key matches both.
	 *
	 * @return void
	 */
	public function test_retained_wins_over_legacy_overlap(): void {
		$rules = new KT_Cleanup_Key_Rules(
			array( 'exact' => array( 'widgets_0_keep' ) ),
			array(
				'postmeta' => array(
					'exact'    => array(),
					'patterns' => array( '/^widgets(_.*)?$/' ),
				),
			)
		);

		$this->assertTrue( $rules->is_legacy( 'postmeta', 'widgets_0_keep' ) );
		$this->assertSame( KT_Cleanup_Key_Rules::KEEP, $rules->decide( 'postmeta', 'widgets_0_keep', KT_Cleanup_Key_Rules::SCOPE_LIVE ) );
		$this->expectException( RuntimeException::class );
		$rules->assert_deletable( 'postmeta', '_widgets_0_keep', KT_Cleanup_Key_Rules::SCOPE_LIVE );
	}

	/**
	 * The guard refuses retained keys on live posts.
	 *
	 * @param string $key Meta key.
	 * @return void
	 */
	#[DataProvider( 'retained_keys' )]
	public function test_guard_refuses_retained_on_live_posts( string $key ): void {
		$this->expectException( RuntimeException::class );
		$this->rules->assert_deletable( 'postmeta', $key, KT_Cleanup_Key_Rules::SCOPE_LIVE );
	}

	/**
	 * The guard allows retained keys on revisions and legacy keys anywhere.
	 *
	 * @return void
	 */
	public function test_guard_allows_revision_copies_and_legacy_rows(): void {
		$this->rules->assert_deletable( 'postmeta', 'house_photos', KT_Cleanup_Key_Rules::SCOPE_REVISION );
		$this->rules->assert_deletable( 'postmeta', 'widgets_0_body', KT_Cleanup_Key_Rules::SCOPE_LIVE );
		$this->rules->assert_deletable( 'termmeta', 'tax_color_scheme', KT_Cleanup_Key_Rules::SCOPE_LIVE );
		$this->addToAssertionCount( 3 );
	}

	/**
	 * The guard refuses keys that are in neither list.
	 *
	 * @return void
	 */
	public function test_guard_refuses_unknown_keys(): void {
		$this->expectException( RuntimeException::class );
		$this->rules->assert_deletable( 'postmeta', '_wp_attachment_metadata', KT_Cleanup_Key_Rules::SCOPE_REVISION );
	}

	/**
	 * Retained keys are never deleted from termmeta, even "revision" scope.
	 *
	 * @return void
	 */
	public function test_retained_termmeta_never_deleted(): void {
		$this->assertSame( KT_Cleanup_Key_Rules::KEEP, $this->rules->decide( 'termmeta', 'house_photos', KT_Cleanup_Key_Rules::SCOPE_REVISION ) );
	}

	/**
	 * Termmeta legacy keys.
	 *
	 * @return void
	 */
	public function test_termmeta_legacy_keys(): void {
		$this->assertTrue( $this->rules->is_legacy( 'termmeta', '_taxonomy_intro_image' ) );
		$this->assertTrue( $this->rules->is_legacy( 'termmeta', 'top_widgets_0_gallery' ) );
		$this->assertFalse( $this->rules->is_legacy( 'termmeta', 'CLIdefaultstate' ) );
		$this->assertFalse( $this->rules->is_legacy( 'termmeta', 'lower_widgets_0_body' ) );
	}

	/**
	 * LIKE wildcards are escaped exactly as wpdb::esc_like() does.
	 *
	 * @return void
	 */
	public function test_esc_like(): void {
		$this->assertSame( 'widgets\_', KT_Cleanup_Key_Rules::esc_like( 'widgets_' ) );
		$this->assertSame( '\_widgets\_', KT_Cleanup_Key_Rules::esc_like( '_widgets_' ) );
		$this->assertSame( '100\%', KT_Cleanup_Key_Rules::esc_like( '100%' ) );
		$this->assertSame( 'a\\\\b', KT_Cleanup_Key_Rules::esc_like( 'a\\b' ) );
	}

	/**
	 * The pre-filter escapes every prefix and includes the ACF ref forms.
	 *
	 * @return void
	 */
	public function test_legacy_prefilter_is_escaped(): void {
		$filter = $this->rules->legacy_prefilter( 'postmeta' );

		$this->assertContains( 'widgets%', $filter['like'] );
		$this->assertContains( '\_widgets%', $filter['like'] );
		$this->assertContains( 'availability\_calendar\_%', $filter['like'] );
		$this->assertContains( '\_availability\_calendar\_%', $filter['like'] );
		$this->assertContains( 'brief_description_winter', $filter['in'] );
		$this->assertContains( '_brief_description_winter', $filter['in'] );

		foreach ( $filter['like'] as $like ) {
			$body = substr( $like, 0, -1 );
			$this->assertDoesNotMatchRegularExpression( '/(?<!\\\\)[_%]/', $body, "Unescaped wildcard in {$like}" );
		}
	}

	/**
	 * Every pattern compiles, is anchored and yields a literal prefix.
	 *
	 * @return void
	 */
	public function test_literal_prefix(): void {
		$this->assertSame( 'availability_calendar_', KT_Cleanup_Key_Rules::literal_prefix( '/^availability_calendar_\d+_(month)$/' ) );
		$this->assertSame( 'widgets', KT_Cleanup_Key_Rules::literal_prefix( '/^widgets(_.*)?$/' ) );
	}

	/**
	 * Unanchored or prefix-less patterns are rejected, so no rule can widen to the whole table.
	 *
	 * @return array<string, array{string}>
	 */
	public static function bad_patterns(): array {
		return array(
			'unanchored'   => array( '/widgets/' ),
			'no prefix'    => array( '/^(widgets|lower_widgets)$/' ),
			'match all'    => array( '/^.*$/' ),
			'invalid'      => array( '/^widgets(/' ),
		);
	}

	/**
	 * Bad patterns throw.
	 *
	 * @param string $pattern Regex.
	 * @return void
	 */
	#[DataProvider( 'bad_patterns' )]
	public function test_bad_patterns_rejected( string $pattern ): void {
		$this->expectException( InvalidArgumentException::class );
		new KT_Cleanup_Key_Rules(
			array(),
			array(
				'postmeta' => array(
					'exact'    => array(),
					'patterns' => array( $pattern ),
				),
			)
		);
	}

	/**
	 * Repeater indexes collapse for reporting.
	 *
	 * @return void
	 */
	public function test_shape(): void {
		$this->assertSame( '_widgets_N_buttons_N_button_link', KT_Cleanup_Key_Rules::shape( '_widgets_3_buttons_12_button_link' ) );
		$this->assertSame( 'availability_calendar_N_rates_N_rate_11', KT_Cleanup_Key_Rules::shape( 'availability_calendar_0_rates_4_rate_11' ) );
		$this->assertSame( 'keyfacts_1', KT_Cleanup_Key_Rules::shape( 'keyfacts_1' ) );
	}
}
