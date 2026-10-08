<?php
/**
 * Unit tests for KT_Media_Cleanup_Rules (BugHerd #423, Phase 3).
 *
 * @package kate-toms-core
 */

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Covers CSV parsing, the live re-check and the archive path guard.
 */
final class MediaCleanupRulesTest extends TestCase {

	/**
	 * Header written by `wp media-audit scan --report`.
	 *
	 * @var string[]
	 */
	private const HEADER = array( 'id', 'url', 'file', 'upload_date', 'mime', 'status', 'reasons', 'bytes', 'parent_id', 'parent_title', 'audited_at' );

	/**
	 * Build a CSV row in header order.
	 *
	 * @param int    $id     ID.
	 * @param string $status Status.
	 * @param string $file   File.
	 * @return array
	 */
	private static function row( int $id, string $status, string $file = '' ): array {
		return array( (string) $id, 'https://x/' . $file, $file, '2015-01-01 00:00:00', 'image/jpeg', $status, '', '0', '0', '', '2026-10-08T00:00:00+00:00' );
	}

	/**
	 * A live state that would pass the re-check for the given file.
	 *
	 * @param string $file File.
	 * @return array
	 */
	private static function live( string $file ): array {
		return array(
			'post_type'     => 'attachment',
			'comment_count' => 0,
			'file'          => $file,
			'status'        => 'unused',
			'reasons'       => array(),
		);
	}

	/**
	 * Only unused rows become candidates, sorted by ID, duplicates counted.
	 *
	 * @return void
	 */
	public function test_parse_keeps_only_unused_rows(): void {
		$parsed = KT_Media_Cleanup_Rules::parse_csv(
			array(
				self::HEADER,
				self::row( 30, 'unused', '2015/01/c.jpg' ),
				self::row( 10, 'used', '2015/01/a.jpg' ),
				self::row( 20, 'review', '2015/01/b.jpg' ),
				self::row( 5, 'unused', '2015/01/e.jpg' ),
				self::row( 30, 'unused', '2015/01/c.jpg' ),
			)
		);

		$this->assertSame( array( 5, 30 ), array_keys( $parsed['candidates'] ) );
		$this->assertSame( '2015/01/c.jpg', $parsed['candidates'][30]['file'] );
		$this->assertSame( 2, $parsed['ignored'] );
		$this->assertSame( 1, $parsed['duplicates'] );
	}

	/**
	 * Columns are found by name, not position.
	 *
	 * @return void
	 */
	public function test_parse_uses_header_names(): void {
		$parsed = KT_Media_Cleanup_Rules::parse_csv(
			array(
				array( 'status', 'file', 'id' ),
				array( 'unused', '2015/01/a.jpg', '7' ),
			)
		);
		$this->assertSame( '2015/01/a.jpg', $parsed['candidates'][7]['file'] );
	}

	/**
	 * Bad input is rejected rather than guessed at.
	 *
	 * @return array<string, array{array}>
	 */
	public static function bad_csvs(): array {
		return array(
			'empty'          => array( array() ),
			'old csv format' => array( array( array( 'id', 'filename', 'state' ), array( '1', 'a.jpg', 'unused' ) ) ),
			'non-numeric id' => array( array( self::HEADER, self::row( 0, 'unused' ) ) ),
			'id with junk'   => array( array( array( 'id', 'status', 'file' ), array( '12; DROP', 'unused', 'a.jpg' ) ) ),
		);
	}

	/**
	 * Bad CSVs throw.
	 *
	 * @param array $lines Lines.
	 * @return void
	 */
	#[DataProvider( 'bad_csvs' )]
	public function test_parse_rejects_bad_csv( array $lines ): void {
		$this->expectException( InvalidArgumentException::class );
		KT_Media_Cleanup_Rules::parse_csv( $lines );
	}

	/**
	 * Still unused, same file: delete.
	 *
	 * @return void
	 */
	public function test_recheck_allows_still_unused(): void {
		$row = array(
			'id'     => 5,
			'file'   => '2015/01/e.jpg',
			'status' => 'unused',
		);
		$this->assertSame( KT_Media_Cleanup_Rules::DELETE, KT_Media_Cleanup_Rules::recheck( $row, self::live( '2015/01/e.jpg' ) ) );
	}

	/**
	 * Anything that changed since the CSV is skipped.
	 *
	 * @return array<string, array{array|null, string}>
	 */
	public static function changed_states(): array {
		$live = self::live( '2015/01/e.jpg' );
		return array(
			'deleted since the audit'    => array( null, 'gone' ),
			'no longer an attachment'    => array( array( 'post_type' => 'post' ) + $live, 'no longer an attachment' ),
			'now used in content'        => array( array( 'status' => 'used', 'reasons' => array( 'content' ) ) + $live, 'now used content' ),
			'now used via retained key'  => array( array( 'status' => 'used', 'reasons' => array( 'retained' ) ) + $live, 'now used retained' ),
			'now only in review'         => array( array( 'status' => 'review', 'reasons' => array( 'acf' ) ) + $live, 'now review acf' ),
			'file replaced'              => array( array( 'file' => '2016/02/other.jpg' ) + $live, 'file differs' ),
			'has comments'               => array( array( 'comment_count' => 2 ) + $live, 'has comments' ),
			'shares a file'              => array( array( 'shared_with' => array( 14557 ) ) + $live, 'also belong to attachment 14557' ),
		);
	}

	/**
	 * Changed attachments are skipped with a reason.
	 *
	 * @param array|null $live     Live state.
	 * @param string     $expected Fragment of the skip reason.
	 * @return void
	 */
	#[DataProvider( 'changed_states' )]
	public function test_recheck_skips_changes( $live, string $expected ): void {
		$row      = array(
			'id'     => 5,
			'file'   => '2015/01/e.jpg',
			'status' => 'unused',
		);
		$decision = KT_Media_Cleanup_Rules::recheck( $row, $live );

		$this->assertNotSame( KT_Media_Cleanup_Rules::DELETE, $decision );
		$this->assertStringContainsString( $expected, $decision );
	}

	/**
	 * Archive paths must stay inside the archive.
	 *
	 * @return void
	 */
	public function test_safe_relative_paths(): void {
		$this->assertTrue( KT_Media_Cleanup_Rules::is_safe_relative_path( '2015/01/a-300x200.jpg' ) );
		$this->assertTrue( KT_Media_Cleanup_Rules::is_safe_relative_path( 'a..b.jpg' ) );
		foreach ( array( '', '/etc/passwd', '../wp-config.php', '2015/../../x', '2015\\..\\x', 'C:/x', "a\0b" ) as $bad ) {
			$this->assertFalse( KT_Media_Cleanup_Rules::is_safe_relative_path( $bad ), $bad );
		}
	}

	/**
	 * Directory containment, without prefix confusion.
	 *
	 * @return void
	 */
	public function test_is_inside(): void {
		$this->assertTrue( KT_Media_Cleanup_Rules::is_inside( '/srv/site/wp-content/x', '/srv/site' ) );
		$this->assertTrue( KT_Media_Cleanup_Rules::is_inside( '/srv/site', '/srv/site/' ) );
		$this->assertFalse( KT_Media_Cleanup_Rules::is_inside( '/srv/site-archive', '/srv/site' ) );
		$this->assertFalse( KT_Media_Cleanup_Rules::is_inside( '/home/kt/archive', '/srv/site' ) );
	}
}
