<?php
/**
 * Tests for the Symfony env-channel resolution of bundles.php entries.
 *
 * @package AchttienVijftien\ServiceContainer\Test
 */

namespace AchttienVijftien\ServiceContainer\Test;

use AchttienVijftien\ServiceContainer\ServiceContainer;
use PHPUnit\Framework\TestCase;

/**
 * Class EnvironmentChannelTest.
 */
class EnvironmentChannelTest extends TestCase {

	/**
	 * WordPress environment types map to Symfony channels.
	 *
	 * @return void
	 */
	public function test_wp_environments_map_to_symfony_channels(): void {
		self::assertSame( 'dev', ServiceContainer::environment_channel( 'local' ) );
		self::assertSame( 'dev', ServiceContainer::environment_channel( 'development' ) );
		self::assertSame( 'prod', ServiceContainer::environment_channel( 'staging' ) );
		self::assertSame( 'prod', ServiceContainer::environment_channel( 'production' ) );
	}

	/**
	 * Unknown environment types fall back to the prod channel.
	 *
	 * @return void
	 */
	public function test_unknown_environment_falls_back_to_prod(): void {
		self::assertSame( 'prod', ServiceContainer::environment_channel( 'something-else' ) );
	}

	/**
	 * The WordPress name wins over the channel, the channel over 'all'.
	 *
	 * @return void
	 */
	public function test_bundle_environments_resolution_order(): void {
		self::assertTrue( ServiceContainer::bundle_enabled( [ 'local' => true, 'dev' => false ], 'local' ) );
		self::assertFalse( ServiceContainer::bundle_enabled( [ 'development' => false, 'all' => true ], 'development' ) );
		self::assertTrue( ServiceContainer::bundle_enabled( [ 'dev' => true ], 'local' ) );
		self::assertTrue( ServiceContainer::bundle_enabled( [ 'dev' => true, 'test' => true ], 'development' ) );
		self::assertFalse( ServiceContainer::bundle_enabled( [ 'dev' => true, 'test' => true ], 'production' ) );
		self::assertTrue( ServiceContainer::bundle_enabled( [ 'prod' => true ], 'staging' ) );
		self::assertTrue( ServiceContainer::bundle_enabled( [ 'all' => true ], 'production' ) );
		self::assertFalse( ServiceContainer::bundle_enabled( [], 'production' ) );
	}
}
