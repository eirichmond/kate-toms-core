<?php
/**
 * Sanity test proving the standalone PHPUnit harness runs and discovers tests.
 *
 * @package kate-toms-core
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Verifies the test harness itself is wired up correctly.
 */
final class HarnessTest extends TestCase {

	/**
	 * The harness executes and PHPUnit assertions are available.
	 *
	 * @return void
	 */
	public function test_harness_runs(): void {
		$this->assertTrue( true, 'The standalone PHPUnit harness is operational.' );
	}
}
