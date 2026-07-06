<?php
/**
 * Standalone PHPUnit bootstrap for pure-PHP unit tests.
 *
 * These tests exercise business logic that has no WordPress dependency, so no
 * WP test framework or wp-env is loaded. The Composer autoloader is loaded for
 * PHPUnit itself, then any plugin source files under test are required directly.
 *
 * @package kate-toms-core
 */

declare(strict_types=1);

require_once dirname( __DIR__, 2 ) . '/vendor/autoload.php';

/*
 * Pure classes under test. Guarded with file_exists() so the harness can be
 * verified before the class exists (added in task 1.1). Once present, tests can
 * instantiate it directly without WordPress.
 */
$kate_toms_helper = dirname( __DIR__, 2 ) . '/includes/special-offers/class-special-offers-grid.php';

if ( file_exists( $kate_toms_helper ) ) {
	require_once $kate_toms_helper;
}
