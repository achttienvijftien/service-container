<?php
/**
 * Fixtures-scoped kernel for tests.
 *
 * @package AchttienVijftien\ServiceContainer\Test
 */

namespace AchttienVijftien\ServiceContainer\Test;

use AchttienVijftien\ServiceContainer\ServiceContainer;

/**
 * Points config and cache at tests/fixtures.
 */
class FixtureServiceContainer extends ServiceContainer {

	/**
	 * Returns the fixtures config directory.
	 *
	 * @return string
	 */
	protected function get_config_path(): string {
		return __DIR__ . '/fixtures/config';
	}

	/**
	 * Returns the fixtures cache directory.
	 *
	 * @return string
	 */
	protected function get_cache_dir(): string {
		return __DIR__ . '/fixtures/var';
	}
}
