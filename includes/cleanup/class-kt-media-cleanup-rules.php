<?php
/**
 * Decision rules for `wp kt cleanup media` (BugHerd #423, Phase 3).
 *
 * Pure PHP, no WordPress dependency, so the CSV parsing, the live re-check
 * and the archive path guard can be unit-tested directly.
 *
 * @package Kate_Toms_Core
 */

/**
 * Decides whether one audited attachment may be deleted now.
 */
class KT_Media_Cleanup_Rules {

	const DELETE = 'delete';

	/** Columns the CSV must have (from `wp media-audit scan --report`). */
	const REQUIRED_COLUMNS = array( 'id', 'status', 'file' );

	/**
	 * Parse an audit CSV into rows keyed by attachment ID, ascending.
	 *
	 * Only rows with status `unused` are candidates. Everything else is counted.
	 *
	 * @param iterable $lines Rows as arrays (fgetcsv output), header first.
	 * @return array{candidates: array<int, array{id:int, file:string, status:string}>, ignored: int, duplicates: int}
	 * @throws InvalidArgumentException On a missing header column or a bad ID.
	 */
	public static function parse_csv( $lines ) {
		$header     = null;
		$candidates = array();
		$ignored    = 0;
		$duplicates = 0;

		foreach ( $lines as $n => $line ) {
			if ( null === $header ) {
				$header  = array_map( 'trim', (array) $line );
				$missing = array_diff( self::REQUIRED_COLUMNS, $header );
				if ( $missing ) {
					throw new InvalidArgumentException( 'CSV is missing columns: ' . implode( ', ', $missing ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- CLI message.
				}
				$header = array_flip( $header );
				continue;
			}
			if ( array( null ) === $line || array() === $line ) {
				continue;
			}
			$id = trim( (string) ( $line[ $header['id'] ] ?? '' ) );
			if ( ! ctype_digit( $id ) || 0 === (int) $id ) {
				throw new InvalidArgumentException( sprintf( 'CSV line %d has an invalid id "%s".', $n + 1, $id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- CLI message.
			}
			$status = trim( (string) ( $line[ $header['status'] ] ?? '' ) );
			if ( 'unused' !== $status ) {
				++$ignored;
				continue;
			}
			$id = (int) $id;
			if ( isset( $candidates[ $id ] ) ) {
				++$duplicates;
				continue;
			}
			$candidates[ $id ] = array(
				'id'     => $id,
				'file'   => trim( (string) ( $line[ $header['file'] ] ?? '' ) ),
				'status' => $status,
			);
		}

		if ( null === $header ) {
			throw new InvalidArgumentException( 'CSV is empty.' );
		}
		ksort( $candidates );

		return array(
			'candidates' => $candidates,
			'ignored'    => $ignored,
			'duplicates' => $duplicates,
		);
	}

	/**
	 * The live re-check: compare the CSV row with the state right now.
	 *
	 * @param array      $row  Candidate from parse_csv().
	 * @param array|null $live Current state, or null when the post no longer exists:
	 *                         post_type, comment_count, file (_wp_attached_file),
	 *                         status, reasons and shared_with (other attachments
	 *                         owning any of its files) from a fresh Media_Audit_Scanner run.
	 * @return string DELETE, or a human-readable skip reason.
	 */
	public static function recheck( array $row, $live ) {
		if ( null === $live ) {
			return 'gone: attachment no longer exists';
		}
		if ( 'attachment' !== ( $live['post_type'] ?? '' ) ) {
			return 'changed: post is no longer an attachment';
		}
		if ( (int) ( $live['comment_count'] ?? 0 ) > 0 ) {
			return 'changed: attachment has comments (wp_delete_attachment would delete them)';
		}
		if ( 'unused' !== ( $live['status'] ?? '' ) ) {
			$reasons = implode( ' ', (array) ( $live['reasons'] ?? array() ) );
			return trim( sprintf( 'changed: now %s %s', $live['status'] ?? '?', $reasons ) );
		}
		if ( ! empty( $live['shared_with'] ) ) {
			return 'shared: files also belong to attachment ' . implode( ', ', array_map( 'intval', (array) $live['shared_with'] ) );
		}
		$csv_file  = (string) $row['file'];
		$live_file = (string) ( $live['file'] ?? '' );
		if ( $csv_file !== $live_file ) {
			return 'changed: file differs from the CSV';
		}
		return self::DELETE;
	}

	/**
	 * Whether a path relative to uploads is safe to copy into the archive.
	 *
	 * @param string $rel Relative path.
	 * @return bool
	 */
	public static function is_safe_relative_path( $rel ) {
		$rel = (string) $rel;
		if ( '' === $rel || '/' === $rel[0] || '\\' === $rel[0] || false !== strpos( $rel, "\0" ) || preg_match( '#^[a-z]+:#i', $rel ) ) {
			return false;
		}
		foreach ( preg_split( '#[/\\\\]#', $rel ) as $part ) {
			if ( '..' === $part ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Whether a directory is inside another (both must be real paths).
	 *
	 * @param string $dir  Directory.
	 * @param string $root Root.
	 * @return bool
	 */
	public static function is_inside( $dir, $root ) {
		return 0 === strpos( rtrim( $dir, '/' ) . '/', rtrim( $root, '/' ) . '/' );
	}
}
