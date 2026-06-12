<?php
/**
 * Tests when@channel config matching through a real kernel boot.
 *
 * @package AchttienVijftien\ServiceContainer\Test
 */

namespace AchttienVijftien\ServiceContainer\Test;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Boots the real kernel against the fixtures config dir.
 */
class WhenChannelTest extends TestCase {

	/**
	 * Boots a fixtures-scoped kernel for a WordPress environment type.
	 *
	 * @param string $wp_env The WordPress environment type.
	 *
	 * @return ContainerInterface
	 */
	private function boot( string $wp_env ): ContainerInterface {
		$GLOBALS['__wp_env_type'] = $wp_env;

		( new Filesystem() )->remove( __DIR__ . '/fixtures/var' );

		$kernel = new FixtureServiceContainer();
		$kernel->boot();

		return $kernel->get();
	}

	/**
	 * The when@dev block applies on the 'local' WordPress environment.
	 *
	 * @return void
	 */
	public function test_when_dev_matches_on_local(): void {
		self::assertSame( 'dev-value', $this->boot( 'local' )->getParameter( 'flexish' ) );
	}

	/**
	 * The when@prod block applies on the 'production' WordPress environment.
	 *
	 * @return void
	 */
	public function test_when_prod_matches_on_production(): void {
		self::assertSame( 'prod-value', $this->boot( 'production' )->getParameter( 'flexish' ) );
	}

	/**
	 * The parameter surface keeps the WordPress names; the channel never
	 * becomes a parameter (it only drives project-level config loading).
	 *
	 * @return void
	 */
	public function test_environment_parameters_keep_the_wordpress_names(): void {
		$container = $this->boot( 'local' );

		self::assertSame( 'local', $container->getParameter( 'kernel.environment' ) );
		self::assertSame( 'local', $container->getParameter( 'kernel.runtime_environment' ) );
		self::assertFalse( $container->hasParameter( 'kernel.environment_channel' ) );
	}
}
