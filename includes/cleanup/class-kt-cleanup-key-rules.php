<?php
/**
 * Key matching rules for the legacy meta cleanup (BugHerd #423).
 *
 * Pure PHP, no WordPress dependency, so the matching, the LIKE escaping and
 * the retained-keys guard can be unit-tested directly.
 *
 * @package Kate_Toms_Core
 */

/**
 * Decides, per meta row, whether the legacy cleanup may delete it.
 *
 * A row is identified by its table (`postmeta` / `termmeta`), its meta_key and
 * its scope: `live` (owner exists and is not a revision), `revision` or
 * `orphan` (owner no longer exists).
 */
class KT_Cleanup_Key_Rules {

	const DELETE         = 'delete';
	const KEEP           = 'keep';
	const ORPHAN         = 'orphan';
	const SCOPE_LIVE     = 'live';
	const SCOPE_REVISION = 'revision';
	const SCOPE_ORPHAN   = 'orphan';

	/**
	 * Protected keys: exact names and regexes.
	 *
	 * @var array{exact: string[], patterns: string[]}
	 */
	private $protected;

	/**
	 * Legacy keys per table: exact names and regexes.
	 *
	 * @var array<string, array{exact: string[], patterns: string[]}>
	 */
	private $legacy;

	/**
	 * Constructor.
	 *
	 * @param array $retained Array returned by retained-keys.php.
	 * @param array $legacy   Array returned by legacy-keys.php.
	 * @throws InvalidArgumentException When a pattern is invalid or unanchored.
	 */
	public function __construct( array $retained, array $legacy ) {
		$this->protected = array(
			'exact'    => array_values(
				array_merge(
					$retained['exact'] ?? array(),
					$retained['migration_source'] ?? array()
				)
			),
			'patterns' => array_values(
				array_merge(
					$retained['patterns'] ?? array(),
					$retained['migration_source_patterns'] ?? array()
				)
			),
		);

		$this->legacy = array();
		foreach ( $legacy as $table => $rules ) {
			$this->legacy[ $table ] = array(
				'exact'    => array_values( $rules['exact'] ?? array() ),
				'patterns' => array_values( $rules['patterns'] ?? array() ),
			);
		}

		foreach ( array_merge( $this->protected['patterns'], ...array_column( $this->legacy, 'patterns' ) ) as $pattern ) {
			self::literal_prefix( $pattern );
		}
	}

	/**
	 * Build the rules from the plugin's two key files.
	 *
	 * @return self
	 */
	public static function from_files() {
		return new self(
			require __DIR__ . '/retained-keys.php',
			require __DIR__ . '/legacy-keys.php'
		);
	}

	/**
	 * Tables this rule set covers.
	 *
	 * @return string[]
	 */
	public function tables() {
		return array_keys( $this->legacy );
	}

	/**
	 * Whether the key is retained (live or migration source).
	 *
	 * @param string $key Meta key.
	 * @return bool
	 */
	public function is_protected( $key ) {
		return self::matches( $key, $this->protected['exact'], $this->protected['patterns'] );
	}

	/**
	 * Whether the key is a legacy key for the given table.
	 *
	 * @param string $table `postmeta` or `termmeta`.
	 * @param string $key   Meta key.
	 * @return bool
	 */
	public function is_legacy( $table, $key ) {
		if ( ! isset( $this->legacy[ $table ] ) ) {
			return false;
		}
		return self::matches( $key, $this->legacy[ $table ]['exact'], $this->legacy[ $table ]['patterns'] );
	}

	/**
	 * Decide what happens to one row.
	 *
	 * - Retained keys are kept on live posts and orphans, and deleted from
	 *   revisions (nothing reads revision meta).
	 * - Legacy keys are deleted, except orphans, which are only reported.
	 * - Anything else is out of scope (null) and never touched.
	 *
	 * @param string $table `postmeta` or `termmeta`.
	 * @param string $key   Meta key.
	 * @param string $scope One of the SCOPE_* constants.
	 * @return string|null DELETE, KEEP, ORPHAN or null.
	 */
	public function decide( $table, $key, $scope ) {
		if ( $this->is_protected( $key ) ) {
			return ( 'postmeta' === $table && self::SCOPE_REVISION === $scope ) ? self::DELETE : self::KEEP;
		}
		if ( ! $this->is_legacy( $table, $key ) ) {
			return null;
		}
		return self::SCOPE_ORPHAN === $scope ? self::ORPHAN : self::DELETE;
	}

	/**
	 * Hard guard, run on every row immediately before it is deleted.
	 *
	 * @param string $table `postmeta` or `termmeta`.
	 * @param string $key   Meta key.
	 * @param string $scope One of the SCOPE_* constants.
	 * @return void
	 * @throws RuntimeException When the row must not be deleted.
	 */
	public function assert_deletable( $table, $key, $scope ) {
		if ( $this->is_protected( $key ) && ! ( 'postmeta' === $table && self::SCOPE_REVISION === $scope ) ) {
			throw new RuntimeException( sprintf( 'Refusing to delete retained key "%s" (%s, %s).', $key, $table, $scope ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- CLI message, not HTML.
		}
		if ( ! $this->is_protected( $key ) && ! $this->is_legacy( $table, $key ) ) {
			throw new RuntimeException( sprintf( 'Refusing to delete non-legacy key "%s" (%s).', $key, $table ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- CLI message, not HTML.
		}
	}

	/**
	 * SQL pre-filter for the legacy keys of a table.
	 *
	 * Returns exact keys for an IN() list and escaped LIKE strings. Each key or
	 * prefix appears twice: as-is and with the ACF `_` reference prefix. The
	 * pre-filter is deliberately broader than the regexes; decide() is the
	 * authority on every row.
	 *
	 * @param string $table `postmeta` or `termmeta`.
	 * @return array{in: string[], like: string[]}
	 */
	public function legacy_prefilter( $table ) {
		$rules = $this->legacy[ $table ] ?? array(
			'exact'    => array(),
			'patterns' => array(),
		);
		return self::prefilter( $rules['exact'], $rules['patterns'] );
	}

	/**
	 * SQL pre-filter for the retained keys (used to find their revision copies).
	 *
	 * @return array{in: string[], like: string[]}
	 */
	public function protected_prefilter() {
		return self::prefilter( $this->protected['exact'], $this->protected['patterns'] );
	}

	/**
	 * Escape a string for use inside a LIKE pattern.
	 *
	 * Same behaviour as wpdb::esc_like(): `_` and `%` are wildcards in LIKE, so
	 * `widgets_` must become `widgets\_`.
	 *
	 * @param string $text Raw text.
	 * @return string
	 */
	public static function esc_like( $text ) {
		return addcslashes( $text, '_%\\' );
	}

	/**
	 * The literal text a `^`-anchored pattern must start with.
	 *
	 * @param string $pattern Regex using `/` delimiters.
	 * @return string
	 * @throws InvalidArgumentException When the pattern is invalid, unanchored or has no literal prefix.
	 */
	public static function literal_prefix( $pattern ) {
		if ( ! is_string( $pattern ) || false === @preg_match( $pattern, '' ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- validity probe.
			throw new InvalidArgumentException( sprintf( 'Invalid pattern: %s', (string) $pattern ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- CLI message, not HTML.
		}
		if ( 0 !== strpos( $pattern, '/^' ) ) {
			throw new InvalidArgumentException( sprintf( 'Pattern must start with "/^": %s', $pattern ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- CLI message, not HTML.
		}
		$prefix = '';
		$length = strlen( $pattern );
		for ( $i = 2; $i < $length; $i++ ) {
			if ( false !== strpos( '\\.[](){}|*+?^$/', $pattern[ $i ] ) ) {
				break;
			}
			$prefix .= $pattern[ $i ];
		}
		if ( '' === $prefix ) {
			throw new InvalidArgumentException( sprintf( 'Pattern has no literal prefix: %s', $pattern ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- CLI message, not HTML.
		}
		return $prefix;
	}

	/**
	 * Collapse repeater indexes so keys group into readable shapes.
	 *
	 * `_widgets_3_buttons_12_button_link` becomes `_widgets_N_buttons_N_button_link`.
	 *
	 * @param string $key Meta key.
	 * @return string
	 */
	public static function shape( $key ) {
		return preg_replace( '/_\d+(?=_)/', '_N', $key );
	}

	/**
	 * Match a key, or its ACF reference form, against exact keys and regexes.
	 *
	 * @param string   $key      Meta key.
	 * @param string[] $exact    Exact keys.
	 * @param string[] $patterns Regexes.
	 * @return bool
	 */
	private static function matches( $key, array $exact, array $patterns ) {
		$candidates = array( $key );
		if ( '' !== $key && '_' === $key[0] ) {
			$candidates[] = substr( $key, 1 );
		}
		foreach ( $candidates as $candidate ) {
			if ( in_array( $candidate, $exact, true ) ) {
				return true;
			}
			foreach ( $patterns as $pattern ) {
				if ( preg_match( $pattern, $candidate ) ) {
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * Build IN() and LIKE lists for a set of exact keys and patterns.
	 *
	 * @param string[] $exact    Exact keys.
	 * @param string[] $patterns Regexes.
	 * @return array{in: string[], like: string[]}
	 */
	private static function prefilter( array $exact, array $patterns ) {
		$in = array();
		foreach ( $exact as $key ) {
			$in[] = $key;
			if ( '_' !== $key[0] ) {
				$in[] = '_' . $key;
			}
		}
		$like = array();
		foreach ( $patterns as $pattern ) {
			$prefix = self::literal_prefix( $pattern );
			$like[] = self::esc_like( $prefix ) . '%';
			$like[] = self::esc_like( '_' . $prefix ) . '%';
		}
		return array(
			'in'   => array_values( array_unique( $in ) ),
			'like' => array_values( array_unique( $like ) ),
		);
	}
}
