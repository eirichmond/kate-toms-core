<?php
/**
 * `wp kt cleanup` — legacy data cleanup commands (BugHerd #423).
 *
 * Every subcommand is read-only unless `--yes` is passed: no DB writes, no
 * transients, no flags. All runs take a MySQL named lock, work in ascending-ID
 * batches with a pause between them, log to a file, and can be resumed with
 * `--from-id`.
 *
 * Usage:
 *   wp kt cleanup legacy-meta --log=~/kt423/dry.log    # dry run (default)
 *   wp kt cleanup legacy-meta --yes --log=~/kt423/run.log --export=~/kt423/legacy-meta.sql
 *   wp kt cleanup media --from=~/kt423/audit.csv --log=~/kt423/media.log     # dry run
 *   wp kt cleanup media --from=~/kt423/audit.csv --log=~/kt423/media.log --yes --archive=~/kt423/media-archive
 *
 * Rollback: `wp db query < <export>.sql` re-inserts every deleted row with its
 * original ID. For media, also copy <archive>/uploads/ back into wp-content/uploads/.
 *
 * @package Kate_Toms_Core
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

/**
 * Remove legacy clubsandwich / ACF data that the block site no longer reads.
 */
class KT_Cleanup_CLI_Command extends WP_CLI_Command {

	const LOCK_PREFIX = 'kt_cleanup_';

	/**
	 * Key rules.
	 *
	 * @var KT_Cleanup_Key_Rules
	 */
	private $rules;

	/**
	 * Open log file handle.
	 *
	 * @var resource|null
	 */
	private $log;

	/**
	 * Open export file handle.
	 *
	 * @var resource|null
	 */
	private $export;

	/**
	 * Remove legacy widget / ACF post meta and term meta.
	 *
	 * Retained keys (includes/cleanup/retained-keys.php) are never deleted from
	 * live posts or terms; their copies on revisions are. Orphaned meta (owner
	 * gone) is reported separately and only deleted with --orphans.
	 *
	 * ## OPTIONS
	 *
	 * [--dry-run]
	 * : Report only. This is the default; deletion requires --yes.
	 *
	 * [--yes]
	 * : Actually delete. Requires --export.
	 *
	 * [--export=<file>]
	 * : Write every row as re-insertable SQL here before it is deleted. Must be a new file outside the web root.
	 *
	 * --log=<file>
	 * : Log file (required). Appended to if it exists. Keep it outside the web root.
	 *
	 * [--table=<table>]
	 * : Which meta table to process.
	 * ---
	 * default: all
	 * options:
	 *   - all
	 *   - postmeta
	 *   - termmeta
	 * ---
	 *
	 * [--batch=<n>]
	 * : Rows per delete batch.
	 * ---
	 * default: 500
	 * ---
	 *
	 * [--chunk=<n>]
	 * : meta_id range scanned per counting query in a dry run.
	 * ---
	 * default: 50000
	 * ---
	 *
	 * [--sleep=<ms>]
	 * : Pause between batches / chunks, in milliseconds.
	 * ---
	 * default: 200
	 * ---
	 *
	 * [--from-id=<id>]
	 * : Start at this meta_id (resume). Applies to each table processed.
	 * ---
	 * default: 1
	 * ---
	 *
	 * [--orphans]
	 * : Also delete legacy-key meta whose post / term no longer exists.
	 *
	 * [--by=<grouping>]
	 * : Group the report by key shape (repeater indexes collapsed) or exact key.
	 * ---
	 * default: shape
	 * options:
	 *   - shape
	 *   - key
	 * ---
	 *
	 * [--network]
	 * : Run on every site in the network, one after another.
	 *
	 * ## EXAMPLES
	 *
	 *     wp kt cleanup legacy-meta --log=~/kt423/dry.log
	 *     wp kt cleanup legacy-meta --table=termmeta --log=~/kt423/dry.log
	 *     wp kt cleanup legacy-meta --yes --log=~/kt423/run.log --export=~/kt423/legacy-meta.sql --sleep=500
	 *     wp kt cleanup legacy-meta --yes --log=~/kt423/run.log --export=~/kt423/legacy-meta-2.sql --from-id=4200001
	 *
	 * @subcommand legacy-meta
	 *
	 * @param array $args       Positional args.
	 * @param array $assoc_args Associative args.
	 * @return void
	 */
	public function legacy_meta( $args, $assoc_args ) {
		$apply   = (bool) \WP_CLI\Utils\get_flag_value( $assoc_args, 'yes', false );
		$dry_run = (bool) \WP_CLI\Utils\get_flag_value( $assoc_args, 'dry-run', false );
		$opts    = array(
			'apply'   => $apply,
			'batch'   => max( 1, (int) $assoc_args['batch'] ),
			'chunk'   => max( 1000, (int) $assoc_args['chunk'] ),
			'sleep'   => max( 0, (int) $assoc_args['sleep'] ),
			'from_id' => max( 1, (int) $assoc_args['from-id'] ),
			'orphans' => (bool) \WP_CLI\Utils\get_flag_value( $assoc_args, 'orphans', false ),
			'by'      => $assoc_args['by'],
		);

		if ( $apply && $dry_run ) {
			WP_CLI::error( '--dry-run and --yes are mutually exclusive.' );
		}
		if ( $apply && empty( $assoc_args['export'] ) ) {
			WP_CLI::error( '--yes requires --export=<file>. No export file, no delete.' );
		}
		$network = (bool) \WP_CLI\Utils\get_flag_value( $assoc_args, 'network', false );
		if ( $network && ! is_multisite() ) {
			WP_CLI::error( '--network given but this is not a multisite install.' );
		}

		$this->rules = KT_Cleanup_Key_Rules::from_files();
		$tables      = 'all' === $assoc_args['table'] ? $this->rules->tables() : array( $assoc_args['table'] );

		$this->acquire_lock( 'legacy_meta' );
		$this->open_log( $assoc_args['log'] );
		if ( $apply ) {
			$this->open_export( $assoc_args['export'] );
		}

		$this->log( sprintf( 'Start legacy-meta: mode=%s tables=%s %s', $apply ? 'DELETE' : 'dry-run', implode( ',', $tables ), wp_json_encode( $opts ) ) );

		$blogs = $network ? array_map(
			'intval',
			get_sites(
				array(
					'fields' => 'ids',
					'number' => 0,
				)
			)
		) : array( get_current_blog_id() );

		foreach ( $blogs as $blog_id ) {
			if ( $network ) {
				switch_to_blog( $blog_id );
			}
			WP_CLI::log( '' );
			WP_CLI::log( WP_CLI::colorize( sprintf( '%%B== Blog %d (%s)%%n', $blog_id, home_url() ) ) );
			foreach ( $tables as $table ) {
				$this->process_meta_table( $table, $opts );
			}
			if ( $network ) {
				restore_current_blog();
			}
		}

		$this->log( 'Done.' );
		$this->close();
		WP_CLI::success( $apply ? 'Legacy meta cleanup finished.' : 'Dry run finished. Nothing was changed.' );
	}

	/**
	 * Permanently delete unused media listed in a `wp media-audit scan` CSV.
	 *
	 * Only rows whose CSV status is `unused` are candidates. Each batch is
	 * re-scanned live (Media_Audit_Scanner, scoped to the batch's IDs) right
	 * before deletion; anything no longer unused, gone, changed, commented or
	 * sharing a file with another attachment is skipped and reported. Before deleting, every file (original, scaled
	 * original, generated sizes, edit backups) is copied into --archive with
	 * its uploads path, and the attachment's posts / postmeta /
	 * term_relationships rows are written there as re-insertable SQL.
	 * Deletion uses wp_delete_attachment( $id, true ).
	 *
	 * ## OPTIONS
	 *
	 * --from=<csv>
	 * : CSV from `wp media-audit scan --report`.
	 *
	 * --log=<file>
	 * : Log file (required, appended to).
	 *
	 * [--dry-run]
	 * : Report only. This is the default; deletion requires --yes.
	 *
	 * [--yes]
	 * : Actually delete. Requires --archive.
	 *
	 * [--archive=<dir>]
	 * : Archive directory outside the web root. Files go to <dir>/uploads/<path>, rows to <dir>/kt-media-cleanup-<time>.sql.
	 *
	 * [--batch=<n>]
	 * : Attachments per batch (one live re-scan per batch).
	 * ---
	 * default: 200
	 * ---
	 *
	 * [--sleep=<ms>]
	 * : Pause between batches and between re-scan queries, in milliseconds.
	 * ---
	 * default: 200
	 * ---
	 *
	 * [--from-id=<id>]
	 * : Skip candidates below this attachment ID (resume).
	 * ---
	 * default: 1
	 * ---
	 *
	 * [--limit=<n>]
	 * : Process at most this many candidates (0 = all). Useful for a small first run.
	 * ---
	 * default: 0
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp kt cleanup media --from=~/kt423/audit.csv --log=~/kt423/media-dry.log
	 *     wp kt cleanup media --from=~/kt423/audit.csv --log=~/kt423/media.log --yes --archive=~/kt423/media-archive --limit=50
	 *
	 * @subcommand media
	 *
	 * @param array $args       Positional args.
	 * @param array $assoc_args Associative args.
	 * @return void
	 */
	public function media( $args, $assoc_args ) {
		if ( ! class_exists( 'Media_Audit_Scanner' ) ) {
			WP_CLI::error( 'The media-audit mu-plugin (v0.2.0+, Media_Audit_Scanner) is required for the live re-check.' );
		}
		if ( is_multisite() && ! is_main_site() ) {
			WP_CLI::error( 'Single-site only: run it on the main site.' );
		}

		$apply = (bool) \WP_CLI\Utils\get_flag_value( $assoc_args, 'yes', false );
		if ( $apply && \WP_CLI\Utils\get_flag_value( $assoc_args, 'dry-run', false ) ) {
			WP_CLI::error( '--dry-run and --yes are mutually exclusive.' );
		}
		if ( $apply && empty( $assoc_args['archive'] ) ) {
			WP_CLI::error( '--yes requires --archive=<dir>. No archive, no delete.' );
		}
		$opts = array(
			'apply'   => $apply,
			'batch'   => max( 1, (int) $assoc_args['batch'] ),
			'sleep'   => max( 0, (int) $assoc_args['sleep'] ),
			'from_id' => max( 1, (int) $assoc_args['from-id'] ),
			'limit'   => max( 0, (int) $assoc_args['limit'] ),
		);

		$csv  = (string) preg_replace( '#^~(?=/)#', (string) getenv( 'HOME' ), $assoc_args['from'] );
		$file = new SplFileObject( $csv, 'r' );
		$file->setFlags( SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY | SplFileObject::READ_AHEAD );
		try {
			$parsed = KT_Media_Cleanup_Rules::parse_csv( $file );
		} catch ( InvalidArgumentException $e ) {
			WP_CLI::error( $e->getMessage() );
		}

		$candidates = array_filter(
			$parsed['candidates'],
			static function ( $row ) use ( $opts ) {
				return $row['id'] >= $opts['from_id'];
			}
		);
		if ( $opts['limit'] ) {
			$candidates = array_slice( $candidates, 0, $opts['limit'], true );
		}

		$this->acquire_lock( 'media' );
		$this->open_log( $assoc_args['log'] );

		$base    = untrailingslashit( wp_get_upload_dir()['basedir'] );
		$archive = null;
		if ( $apply ) {
			$archive = $this->prepare_archive( $assoc_args['archive'], $base );
			$this->open_export( $archive . '/kt-media-cleanup-' . gmdate( 'Ymd-His' ) . '.sql', 'media' );
		}

		$intro = sprintf(
			'Start media: mode=%s csv=%s unused-in-csv=%d other-status=%d duplicates=%d selected=%d %s',
			$apply ? 'DELETE' : 'dry-run',
			$csv,
			count( $parsed['candidates'] ),
			$parsed['ignored'],
			$parsed['duplicates'],
			count( $candidates ),
			wp_json_encode( $opts )
		);
		WP_CLI::log( $intro );
		$this->log( $intro );

		$totals  = array(
			'deleted' => 0,
			'files'   => 0,
			'bytes'   => 0,
		);
		$skipped = array();
		$items   = array();
		$scanner = null;
		$batches = array_chunk( $candidates, $opts['batch'], true );

		if ( ! $apply && $candidates ) {
			// A dry run re-checks everything with one scan.
			$scanner = $this->live_scan( array_keys( $candidates ), $opts['sleep'] );
		}

		foreach ( $batches as $n => $batch ) {
			$ids = array_keys( $batch );
			if ( $apply ) {
				$scanner = $this->live_scan( $ids, $opts['sleep'] );
			}
			$live = $this->live_state( $ids, $scanner );

			$doomed = array();
			foreach ( $batch as $id => $row ) {
				$decision = KT_Media_Cleanup_Rules::recheck( $row, $live[ $id ] ?? null );
				if ( KT_Media_Cleanup_Rules::DELETE !== $decision ) {
					$skipped[ $decision ] = ( $skipped[ $decision ] ?? 0 ) + 1;
					$this->log( "skip {$id}: {$decision}" );
					continue;
				}
				$files = $scanner->files( $id );
				$bytes = 0;
				foreach ( $files as $rel ) {
					if ( ! KT_Media_Cleanup_Rules::is_safe_relative_path( $rel ) ) {
						$this->close();
						WP_CLI::error( "Unsafe file path for attachment {$id}: {$rel}" );
					}
					if ( is_file( "{$base}/{$rel}" ) ) {
						$bytes += (int) filesize( "{$base}/{$rel}" );
					}
				}
				$doomed[ $id ] = array(
					'files' => $files,
					'bytes' => $bytes,
				);
				$items[]       = array(
					'id'    => $id,
					'file'  => $row['file'],
					'files' => count( $files ),
					'bytes' => $bytes,
				);
			}

			if ( $apply && $doomed ) {
				foreach ( $doomed as $id => $d ) {
					$copied = $this->archive_files( $id, $d['files'], $base, $archive );
					$this->log( sprintf( 'archived %d: %d of %d files', $id, $copied, count( $d['files'] ) ) );
				}
				$this->export_attachment_rows( array_keys( $doomed ) );

				foreach ( $doomed as $id => $d ) {
					$result = wp_delete_attachment( $id, true );
					if ( ! $result || get_post( $id ) ) {
						$this->log( "ERROR delete {$id} failed" );
						$this->close();
						WP_CLI::error( "wp_delete_attachment( {$id} ) failed. Resume with --from-id={$id}." );
					}
					++$totals['deleted'];
					$totals['files'] += count( $d['files'] );
					$totals['bytes'] += $d['bytes'];
					$this->log( sprintf( 'deleted %d (%d files, %d bytes)', $id, count( $d['files'] ), $d['bytes'] ) );
				}
			} else {
				foreach ( $doomed as $d ) {
					$totals['files'] += count( $d['files'] );
					$totals['bytes'] += $d['bytes'];
				}
			}

			$last = max( $ids );
			$this->log( sprintf( 'batch %d/%d done, last id %d (resume with --from-id=%d)', $n + 1, count( $batches ), $last, $last + 1 ) );
			if ( $apply ) {
				WP_CLI::log( sprintf( '  batch %d/%d: %d deleted so far', $n + 1, count( $batches ), $totals['deleted'] ) );
			}
			if ( $n + 1 < count( $batches ) ) {
				$this->pause( $opts['sleep'] );
			}
		}

		if ( $items ) {
			\WP_CLI\Utils\format_items( 'table', $items, array( 'id', 'file', 'files', 'bytes' ) );
		}
		foreach ( $skipped as $reason => $count ) {
			WP_CLI::log( sprintf( 'Skipped %d: %s', $count, $reason ) );
		}
		$summary = sprintf(
			'%s %d attachments, %d files, %d bytes (%s). Skipped %d.',
			$apply ? 'Deleted' : 'Would delete',
			$apply ? $totals['deleted'] : count( $items ),
			$totals['files'],
			$totals['bytes'],
			size_format( $totals['bytes'], 1 ),
			array_sum( $skipped )
		);
		WP_CLI::log( $summary );
		$this->log( $summary );
		$this->log( 'Done.' );
		$this->close();
		WP_CLI::success( $apply ? 'Media cleanup finished.' : 'Dry run finished. Nothing was changed.' );
	}

	/**
	 * Fresh reference scan scoped to some attachment IDs.
	 *
	 * @param int[] $ids   Attachment IDs.
	 * @param int   $sleep Pause between scan queries, ms.
	 * @return Media_Audit_Scanner
	 */
	private function live_scan( array $ids, $sleep ) {
		$scanner = new Media_Audit_Scanner(
			array(
				'only_ids' => $ids,
				'sleep'    => $sleep,
				'logger'   => function ( $message ) {
					$this->log( 'scan: ' . $message );
				},
			)
		);
		if ( ! $scanner->has_retained() ) {
			$this->close();
			WP_CLI::error( 'retained-keys.php could not be loaded by the scanner.' );
		}
		$scanner->run();
		return $scanner;
	}

	/**
	 * Current state of some attachments, for KT_Media_Cleanup_Rules::recheck().
	 *
	 * @param int[]               $ids     Attachment IDs.
	 * @param Media_Audit_Scanner $scanner Scan covering these IDs.
	 * @return array<int, array>
	 */
	private function live_state( array $ids, Media_Audit_Scanner $scanner ) {
		global $wpdb;

		$in    = implode( ',', array_map( 'intval', $ids ) );
		$state = array();
		$rows  = $wpdb->get_results( "SELECT p.ID, p.post_type, p.comment_count, f.meta_value AS file FROM {$wpdb->posts} p LEFT JOIN {$wpdb->postmeta} f ON f.post_id = p.ID AND f.meta_key = '_wp_attached_file' WHERE p.ID IN ({$in})" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
		foreach ( $rows as $row ) {
			$id           = (int) $row->ID;
			$state[ $id ] = array(
				'post_type'     => $row->post_type,
				'comment_count' => (int) $row->comment_count,
				'file'          => (string) $row->file,
				'status'        => $scanner->status( $id ),
				'reasons'       => $scanner->reasons( $id ),
				'shared_with'   => $scanner->shared_with( $id ),
			);
		}
		return $state;
	}

	/**
	 * Create / validate the archive directory: outside the web root and uploads.
	 *
	 * @param string $path Requested directory.
	 * @param string $base Uploads base directory.
	 * @return string Real path.
	 */
	private function prepare_archive( $path, $base ) {
		$path = (string) preg_replace( '#^~(?=/)#', (string) getenv( 'HOME' ), $path );
		if ( ! is_dir( $path ) && ! wp_mkdir_p( $path ) ) {
			WP_CLI::error( "Cannot create archive directory {$path}." );
		}
		$real = realpath( $path );
		foreach ( array( realpath( ABSPATH ), realpath( $base ) ) as $root ) {
			if ( $root && KT_Media_Cleanup_Rules::is_inside( $real, $root ) ) {
				WP_CLI::error( "The archive must be outside {$root}." );
			}
		}
		if ( ! wp_is_writable( $real ) ) {
			WP_CLI::error( "Archive directory {$real} is not writable." );
		}
		WP_CLI::log( "Archiving to {$real}" );
		return $real;
	}

	/**
	 * Copy an attachment's files into the archive, verified by checksum.
	 *
	 * @param int      $id      Attachment ID.
	 * @param string[] $files   Paths relative to uploads.
	 * @param string   $base    Uploads base directory.
	 * @param string   $archive Archive directory.
	 * @return int Files copied (missing source files are logged, not fatal).
	 */
	private function archive_files( $id, array $files, $base, $archive ) {
		$copied = 0;
		foreach ( $files as $rel ) {
			$src = "{$base}/{$rel}";
			if ( ! is_file( $src ) ) {
				$this->log( "archive {$id}: missing on disk, nothing to copy: {$rel}" );
				continue;
			}
			$dest = "{$archive}/uploads/{$rel}";
			if ( ! is_dir( dirname( $dest ) ) && ! wp_mkdir_p( dirname( $dest ) ) ) {
				$this->close();
				WP_CLI::error( 'Cannot create ' . dirname( $dest ) . '. Nothing in this batch was deleted.' );
			}
			if ( ! ( is_file( $dest ) && sha1_file( $dest ) === sha1_file( $src ) ) ) {
				if ( ! copy( $src, $dest ) || sha1_file( $dest ) !== sha1_file( $src ) ) {
					$this->close();
					WP_CLI::error( "Archive copy failed for {$rel}. Nothing in this batch was deleted." );
				}
			}
			++$copied;
		}
		return $copied;
	}

	/**
	 * Write the attachments' posts, postmeta and term_relationships rows to the export.
	 *
	 * @param int[] $ids Attachment IDs.
	 * @return void
	 */
	private function export_attachment_rows( array $ids ) {
		global $wpdb;

		$in = implode( ',', array_map( 'intval', $ids ) );
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
		$sets = array(
			$wpdb->posts              => $wpdb->get_results( "SELECT * FROM {$wpdb->posts} WHERE ID IN ({$in})", ARRAY_A ),
			$wpdb->postmeta           => $wpdb->get_results( "SELECT * FROM {$wpdb->postmeta} WHERE post_id IN ({$in})", ARRAY_A ),
			$wpdb->term_relationships => $wpdb->get_results( "SELECT * FROM {$wpdb->term_relationships} WHERE object_id IN ({$in})", ARRAY_A ),
		);
		// phpcs:enable

		$sql = '';
		foreach ( $sets as $table => $rows ) {
			if ( ! $rows ) {
				continue;
			}
			$columns = '`' . implode( '`, `', array_keys( $rows[0] ) ) . '`';
			$values  = array();
			foreach ( $rows as $row ) {
				$values[] = '(' . implode( ', ', array_map( fn( $v ) => null === $v ? 'NULL' : $this->quote( $v ), $row ) ) . ')';
			}
			$sql .= "INSERT INTO `{$table}` ({$columns}) VALUES\n" . implode( ",\n", $values ) . ";\n";
		}

		if ( false === fwrite( $this->export, $sql ) || ! fflush( $this->export ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
			$this->close();
			WP_CLI::error( 'Could not write to the export file. Nothing in this batch was deleted.' );
		}
	}

	/**
	 * Count (dry run) or delete (apply) one meta table on the current blog.
	 *
	 * @param string $table `postmeta` or `termmeta`.
	 * @param array  $opts  Parsed options.
	 * @return void
	 */
	private function process_meta_table( $table, array $opts ) {
		global $wpdb;

		$sql = $this->build_select( $table, $opts['apply'] && ! $opts['orphans'] );

		WP_CLI::log( '' );
		WP_CLI::log( WP_CLI::colorize( sprintf( '%%G-- %s%%n', $this->table_name( $table ) ) ) );
		WP_CLI::log( 'Candidate SQL (every row is then checked by KT_Cleanup_Key_Rules::decide() and the retained-keys guard):' );
		WP_CLI::log( $wpdb->remove_placeholder_escape( $this->prepare_select( $sql['rows'], array( $opts['from_id'] ), $sql['args'], array( $opts['batch'] ) ) ) );
		WP_CLI::log( sprintf( 'Delete SQL: DELETE FROM %s WHERE %s IN (<ids from the batch>)', $this->table_name( $table ), 'meta_id' ) );

		$report = $opts['apply'] ? $this->delete_batches( $table, $sql, $opts ) : $this->count_chunks( $table, $sql, $opts );
		$this->print_report( $table, $report, $opts['apply'] );
	}

	/**
	 * Build the candidate SELECT (batch) and the COUNT (dry run) queries.
	 *
	 * Both are unprepared templates with their key values as `%s` arguments.
	 * Leading placeholders still open: rows = (from_id, ...keys, limit);
	 * count = (from_id, to_id, ...keys). Use prepare_select() to bind them.
	 *
	 * @param string $table        `postmeta` or `termmeta`.
	 * @param bool   $skip_orphans Exclude rows whose owner no longer exists.
	 * @return array{rows: string, count: string, args: string[]}
	 */
	private function build_select( $table, $skip_orphans ) {
		global $wpdb;

		$meta  = $this->table_name( $table );
		$owner = 'postmeta' === $table ? "LEFT JOIN {$wpdb->posts} o ON o.ID = m.post_id" : "LEFT JOIN {$wpdb->terms} o ON o.term_id = m.term_id";
		$ocol  = 'postmeta' === $table ? 'o.ID' : 'o.term_id';
		$type  = 'postmeta' === $table ? 'o.post_type' : "''";
		$fk    = 'postmeta' === $table ? 'm.post_id' : 'm.term_id';
		$args  = array();

		$where = $this->key_condition( $this->rules->legacy_prefilter( $table ), $args );
		if ( 'postmeta' === $table ) {
			// Retained keys are only ever candidates on revisions.
			$where = "( {$where} OR ( o.post_type = 'revision' AND " . $this->key_condition( $this->rules->protected_prefilter(), $args ) . ' ) )';
		}
		if ( $skip_orphans ) {
			$where .= " AND {$ocol} IS NOT NULL";
		}

		$rows = "SELECT m.meta_id AS id, {$fk} AS owner_id, m.meta_key, m.meta_value, {$ocol} AS owner_exists, {$type} AS owner_type
FROM {$meta} m {$owner}
WHERE m.meta_id >= %d AND {$where}
ORDER BY m.meta_id ASC LIMIT %d";

		$count = "SELECT m.meta_key, CASE WHEN {$ocol} IS NULL THEN 'orphan' WHEN {$type} = 'revision' THEN 'revision' ELSE 'live' END AS scope, COUNT(*) AS n, SUM(LENGTH(m.meta_value)) AS bytes
FROM {$meta} m {$owner}
WHERE m.meta_id >= %d AND m.meta_id < %d AND {$where}
GROUP BY m.meta_key, scope";

		return array(
			'rows'  => $rows,
			'count' => $count,
			'args'  => $args,
		);
	}

	/**
	 * Bind a template from build_select().
	 *
	 * @param string $template Query template.
	 * @param int[]  $before   Integer args before the key args.
	 * @param array  $keys     Key args.
	 * @param int[]  $after    Integer args after the key args.
	 * @return string Prepared SQL.
	 */
	private function prepare_select( $template, array $before, array $keys, array $after = array() ) {
		global $wpdb;
		return $wpdb->prepare( $template, array_merge( $before, $keys, $after ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- template only interpolates table/column names.
	}

	/**
	 * SQL condition for a pre-filter (IN list plus escaped LIKEs), with `%s` placeholders.
	 *
	 * @param array{in: string[], like: string[]} $filter Pre-filter.
	 * @param string[]                            $args   Placeholder values, appended by reference.
	 * @return string
	 */
	private function key_condition( array $filter, array &$args ) {
		$parts = array();
		if ( $filter['in'] ) {
			$parts[] = 'm.meta_key IN (' . implode( ', ', array_fill( 0, count( $filter['in'] ), '%s' ) ) . ')';
			array_push( $args, ...$filter['in'] );
		}
		foreach ( $filter['like'] as $like ) {
			$parts[] = 'm.meta_key LIKE %s';
			$args[]  = $like;
		}
		return $parts ? '( ' . implode( ' OR ', $parts ) . ' )' : '( 1 = 0 )';
	}

	/**
	 * Dry run: count candidates in chunks of --chunk rows, pausing between chunks.
	 *
	 * The meta_id column is sparse on production-derived data (13M rows spread over ids up
	 * to 3.3bn), so chunks are cut by row count, not id range: each boundary is
	 * found with an index-only OFFSET on the primary key.
	 *
	 * @param string $table `postmeta` or `termmeta`.
	 * @param array  $sql   Queries from build_select().
	 * @param array  $opts  Parsed options.
	 * @return array Report rows keyed by group.
	 */
	private function count_chunks( $table, array $sql, array $opts ) {
		global $wpdb;

		$name   = $this->table_name( $table );
		$total  = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$name} WHERE meta_id >= %d", $opts['from_id'] ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
		$report = array();
		$start  = $opts['from_id'];

		$progress = \WP_CLI\Utils\make_progress_bar( "Scanning {$name}", (int) ceil( $total / $opts['chunk'] ) );
		while ( null !== $start ) {
			$next = $wpdb->get_var( $wpdb->prepare( "SELECT meta_id FROM {$name} WHERE meta_id >= %d ORDER BY meta_id LIMIT 1 OFFSET %d", $start, $opts['chunk'] ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
			$end  = null === $next ? PHP_INT_MAX : (int) $next;

			$rows = $wpdb->get_results( $this->prepare_select( $sql['count'], array( $start, $end ), $sql['args'] ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery
			foreach ( $rows as $row ) {
				$decision = $this->rules->decide( $table, $row->meta_key, $row->scope );
				$this->tally( $report, $row->meta_key, $row->scope, $decision, (int) $row->n, (int) $row->bytes, $opts['by'] );
			}
			$progress->tick();
			$start = null === $next ? null : $end;
			if ( null !== $start ) {
				$this->pause( $opts['sleep'] );
			}
		}
		$progress->finish();
		$this->log( sprintf( '%s: scanned %d rows from meta_id %d', $name, $total, $opts['from_id'] ) );

		return $report;
	}

	/**
	 * Apply: select a batch, guard every row, export it, then delete it.
	 *
	 * @param string $table `postmeta` or `termmeta`.
	 * @param array  $sql   Queries from build_select().
	 * @param array  $opts  Parsed options.
	 * @return array Report rows keyed by group.
	 */
	private function delete_batches( $table, array $sql, array $opts ) {
		global $wpdb;

		$name    = $this->table_name( $table );
		$id_col  = 'meta_id';
		$fk_col  = 'postmeta' === $table ? 'post_id' : 'term_id';
		$group   = 'postmeta' === $table ? 'post_meta' : 'term_meta';
		$report  = array();
		$next_id = $opts['from_id'];
		$batch_n = 0;

		while ( true ) {
			$rows = $wpdb->get_results( $this->prepare_select( $sql['rows'], array( $next_id ), $sql['args'], array( $opts['batch'] ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery
			if ( ! $rows ) {
				break;
			}
			$next_id = (int) end( $rows )->id + 1;
			++$batch_n;

			$delete = array();
			foreach ( $rows as $row ) {
				$scope    = $this->scope( $row );
				$decision = $this->rules->decide( $table, $row->meta_key, $scope );
				$doomed   = KT_Cleanup_Key_Rules::DELETE === $decision || ( KT_Cleanup_Key_Rules::ORPHAN === $decision && $opts['orphans'] );
				if ( $doomed ) {
					try {
						$this->rules->assert_deletable( $table, $row->meta_key, $scope );
					} catch ( RuntimeException $e ) {
						$this->log( 'GUARD: ' . $e->getMessage() . " {$id_col}={$row->id}" );
						$this->close();
						WP_CLI::error( $e->getMessage() . ' Nothing in this batch was deleted.' );
					}
					$delete[] = $row;
				}
				$this->tally( $report, $row->meta_key, $scope, $doomed ? KT_Cleanup_Key_Rules::DELETE : $decision, 1, strlen( (string) $row->meta_value ), $opts['by'] );
			}

			if ( $delete ) {
				$this->write_export( $name, $id_col, $fk_col, $delete );

				$ids     = implode( ',', array_map( 'intval', wp_list_pluck( $delete, 'id' ) ) );
				$deleted = $wpdb->query( "DELETE FROM {$name} WHERE {$id_col} IN ({$ids})" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
				if ( false === $deleted ) {
					$this->log( "ERROR batch {$batch_n}: " . $wpdb->last_error );
					$this->close();
					WP_CLI::error( "Delete failed in batch {$batch_n}: {$wpdb->last_error}. Resume with --from-id=" . (int) $delete[0]->id );
				}
				foreach ( array_unique( wp_list_pluck( $delete, 'owner_id' ) ) as $owner_id ) {
					wp_cache_delete( (int) $owner_id, $group );
				}
				if ( count( $delete ) !== (int) $deleted ) {
					$this->log( sprintf( 'WARN batch %d: expected %d deletes, got %d', $batch_n, count( $delete ), $deleted ) );
				}
			}

			$this->log( sprintf( '%s batch %d: selected %d, deleted %d, last %s=%d (resume with --from-id=%d)', $name, $batch_n, count( $rows ), count( $delete ), $id_col, $next_id - 1, $next_id ) );
			if ( 0 === $batch_n % 20 ) {
				WP_CLI::log( sprintf( '  %s: up to %s %d', $name, $id_col, $next_id - 1 ) );
			}
			$this->pause( $opts['sleep'] );
		}

		return $report;
	}

	/**
	 * Append rows to the export file as INSERTs, flushed before deletion.
	 *
	 * @param string $name   Table name.
	 * @param string $id_col meta_id column.
	 * @param string $fk_col Owner column.
	 * @param array  $rows   Rows about to be deleted.
	 * @return void
	 */
	private function write_export( $name, $id_col, $fk_col, array $rows ) {
		$values = array();
		foreach ( $rows as $row ) {
			$values[] = sprintf(
				'(%d, %d, %s, %s)',
				$row->id,
				$row->owner_id,
				$this->quote( $row->meta_key ),
				null === $row->meta_value ? 'NULL' : $this->quote( $row->meta_value )
			);
		}
		$sql = "INSERT INTO `{$name}` (`{$id_col}`, `{$fk_col}`, `meta_key`, `meta_value`) VALUES\n" . implode( ",\n", $values ) . ";\n";

		if ( false === fwrite( $this->export, $sql ) || ! fflush( $this->export ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
			$this->close();
			WP_CLI::error( 'Could not write to the export file. Nothing in this batch was deleted.' );
		}
	}

	/**
	 * Add a row (or counted group) to the report.
	 *
	 * @param array       $report   Report, by reference.
	 * @param string      $key      Meta key.
	 * @param string      $scope    live / revision / orphan.
	 * @param string|null $decision Decision from the rules.
	 * @param int         $count    Rows.
	 * @param int         $bytes    meta_value bytes.
	 * @param string      $by       shape or key.
	 * @return void
	 */
	private function tally( array &$report, $key, $scope, $decision, $count, $bytes, $by ) {
		if ( null === $decision ) {
			return;
		}
		$group = 'key' === $by ? $key : KT_Cleanup_Key_Rules::shape( $key );
		if ( ! isset( $report[ $group ] ) ) {
			$report[ $group ] = array(
				'delete_live'     => 0,
				'delete_revision' => 0,
				'orphan'          => 0,
				'kept'            => 0,
				'bytes'           => 0,
			);
		}
		if ( KT_Cleanup_Key_Rules::DELETE === $decision ) {
			$column                       = KT_Cleanup_Key_Rules::SCOPE_REVISION === $scope ? 'delete_revision' : ( KT_Cleanup_Key_Rules::SCOPE_ORPHAN === $scope ? 'orphan' : 'delete_live' );
			$report[ $group ][ $column ] += $count;
			$report[ $group ]['bytes']   += $bytes;
		} elseif ( KT_Cleanup_Key_Rules::ORPHAN === $decision ) {
			$report[ $group ]['orphan'] += $count;
		} else {
			$report[ $group ]['kept'] += $count;
		}
	}

	/**
	 * Print the per-key table and totals.
	 *
	 * @param string $table   `postmeta` or `termmeta`.
	 * @param array  $report  Report rows.
	 * @param bool   $applied Whether rows were deleted.
	 * @return void
	 */
	private function print_report( $table, array $report, $applied ) {
		$verb   = $applied ? 'deleted' : 'would delete';
		$totals = array_fill_keys( array( 'delete_live', 'delete_revision', 'orphan', 'kept', 'bytes' ), 0 );
		$items  = array();

		uasort(
			$report,
			static function ( $a, $b ) {
				return ( $b['delete_live'] + $b['delete_revision'] ) <=> ( $a['delete_live'] + $a['delete_revision'] );
			}
		);
		foreach ( $report as $group => $r ) {
			foreach ( $totals as $k => $v ) {
				$totals[ $k ] += $r[ $k ];
			}
			$items[] = array(
				'key'                   => $group,
				"{$verb} (live)"        => $r['delete_live'],
				"{$verb} (revision)"    => $r['delete_revision'],
				'orphan'                => $r['orphan'],
				'kept (retained, live)' => $r['kept'],
				'MB'                    => round( $r['bytes'] / 1048576, 1 ),
			);
		}

		if ( $items ) {
			\WP_CLI\Utils\format_items( 'table', $items, array_keys( $items[0] ) );
		}
		$summary = sprintf(
			'%s total: %s %s rows (%s live, %s revision), %s MB of meta_value. Orphans %s: %s. Retained rows kept: %s.',
			$this->table_name( $table ),
			$verb,
			number_format( $totals['delete_live'] + $totals['delete_revision'] ),
			number_format( $totals['delete_live'] ),
			number_format( $totals['delete_revision'] ),
			number_format( $totals['bytes'] / 1048576, 1 ),
			$applied ? 'deleted' : 'found (not deleted without --orphans)',
			number_format( $totals['orphan'] ),
			number_format( $totals['kept'] )
		);
		WP_CLI::log( $summary );
		$this->log( $summary );
	}

	/**
	 * Row scope from the joined owner columns.
	 *
	 * @param object $row Row with owner_exists and owner_type.
	 * @return string
	 */
	private function scope( $row ) {
		if ( null === $row->owner_exists ) {
			return KT_Cleanup_Key_Rules::SCOPE_ORPHAN;
		}
		return 'revision' === $row->owner_type ? KT_Cleanup_Key_Rules::SCOPE_REVISION : KT_Cleanup_Key_Rules::SCOPE_LIVE;
	}

	/**
	 * Take a MySQL named lock so two runs cannot overlap. Not a table write.
	 *
	 * @param string $name Lock suffix.
	 * @return void
	 */
	private function acquire_lock( $name ) {
		global $wpdb;

		$lock = self::LOCK_PREFIX . $name . '_' . $wpdb->base_prefix;
		if ( '1' !== (string) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 0)', $lock ) ) ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			WP_CLI::error( "Another run holds the lock {$lock}. Wait for it to finish." );
		}
	}

	/**
	 * Open the log file (append).
	 *
	 * @param string $path Log path.
	 * @return void
	 */
	private function open_log( $path ) {
		$path      = (string) preg_replace( '#^~(?=/)#', (string) getenv( 'HOME' ), $path );
		$this->log = fopen( $path, 'ab' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		if ( ! $this->log ) {
			WP_CLI::error( "Cannot open log file {$path}." );
		}
		WP_CLI::log( "Logging to {$path}" );
	}

	/**
	 * Open the export file: must be new and outside the web root.
	 *
	 * @param string $path  Export path.
	 * @param string $label Command name for the file header.
	 * @return string The export path.
	 */
	private function open_export( $path, $label = 'legacy-meta' ) {
		global $wpdb;

		$path = (string) preg_replace( '#^~(?=/)#', (string) getenv( 'HOME' ), $path );
		$dir  = realpath( dirname( $path ) );
		if ( false === $dir ) {
			WP_CLI::error( 'Export directory does not exist: ' . dirname( $path ) );
		}
		$root = realpath( ABSPATH );
		if ( $root && 0 === strpos( $dir . '/', rtrim( $root, '/' ) . '/' ) ) {
			WP_CLI::error( 'The export contains raw meta values; write it outside the web root (' . $root . ').' );
		}
		$this->export = @fopen( $path, 'xb' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged,WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		if ( ! $this->export ) {
			WP_CLI::error( "Cannot create {$path}. It must not already exist; use a new file per run." );
		}
		fwrite( $this->export, "-- kt cleanup {$label} export " . gmdate( 'c' ) . ' ' . home_url() . "\n-- Rollback: wp db query < this-file\nSET NAMES " . $wpdb->charset . ";\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
		WP_CLI::log( "Exporting deleted rows to {$path}" );
		return $path;
	}

	/**
	 * Write a timestamped line to the log file.
	 *
	 * @param string $message Message.
	 * @return void
	 */
	private function log( $message ) {
		if ( $this->log ) {
			fwrite( $this->log, gmdate( 'Y-m-d H:i:s' ) . ' ' . $message . "\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
		}
	}

	/**
	 * Close files and release the lock.
	 *
	 * @return void
	 */
	private function close() {
		global $wpdb;

		foreach ( array( 'export', 'log' ) as $handle ) {
			if ( $this->$handle ) {
				fclose( $this->$handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
				$this->$handle = null;
			}
		}
		$wpdb->query( 'SELECT RELEASE_ALL_LOCKS()' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * Quote a string as an SQL literal (no placeholder escaping left behind).
	 *
	 * @param string $value Raw value.
	 * @return string
	 */
	private function quote( $value ) {
		global $wpdb;
		return "'" . $wpdb->remove_placeholder_escape( $wpdb->_real_escape( (string) $value ) ) . "'";
	}

	/**
	 * Sleep for the given milliseconds.
	 *
	 * @param int $ms Milliseconds.
	 * @return void
	 */
	private function pause( $ms ) {
		if ( $ms > 0 ) {
			usleep( $ms * 1000 );
		}
	}

	/**
	 * Full table name for the current blog.
	 *
	 * @param string $table `postmeta` or `termmeta`.
	 * @return string
	 */
	private function table_name( $table ) {
		global $wpdb;
		return $wpdb->$table;
	}
}

WP_CLI::add_command( 'kt cleanup', 'KT_Cleanup_CLI_Command' );
