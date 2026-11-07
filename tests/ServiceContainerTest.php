<?php
/**
 * Integration tests booting the container against a fixture project.
 *
 * @package AchttienVijftien\ServiceContainer\Tests
 */

namespace AchttienVijftien\ServiceContainer\Tests;

use AchttienVijftien\ServiceContainer\ServiceContainer;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Each test runs in its own process because compiled container classes and TestKernel can't be unloaded.
 */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState( false )]
class ServiceContainerTest extends TestCase {

	/**
	 * Temporary copy of the fixture project.
	 *
	 * @var string
	 */
	private string $project_dir;

	/**
	 * Copies the fixture project to a temporary directory and autoloads its classes.
	 */
	protected function setUp(): void {
		$this->project_dir = realpath( sys_get_temp_dir() ) . '/service-container-' . bin2hex( random_bytes( 4 ) );

		( new Filesystem() )->mirror( __DIR__ . '/fixtures/project', $this->project_dir );

		spl_autoload_register(
			function ( string $class_name ): void {
				$file = $this->project_dir . '/src/' . str_replace( '\\', '/', $class_name ) . '.php';

				if ( is_file( $file ) ) {
					require $file;
				}
			}
		);
	}

	/**
	 * Removes the temporary project.
	 */
	protected function tearDown(): void {
		( new Filesystem() )->remove( $this->project_dir );
	}

	public function test_boots_container_from_project_config(): void {
		$container = $this->boot( 'local' );

		self::assertSame( $this->project_dir, $container->getParameter( 'kernel.project_dir' ) );
		self::assertSame( 'local', $container->getParameter( 'kernel.environment' ) );
		self::assertTrue( $container->getParameter( 'kernel.debug' ) );
		self::assertSame( [ 'DemoBundle' => \Demo\DemoBundle::class ], $container->getParameter( 'kernel.bundles' ) );
		self::assertTrue( $container->getParameter( 'kernel.runtime_mode.cli' ) );

		self::assertSame( 'local', $container->getParameter( 'env_param' ), 'parameters/{env}.yaml is imported.' );
		self::assertSame( 'hello', $container->getParameter( 'demo.greeting' ), 'packages/*.yaml configures bundle extensions.' );
		self::assertSame( 'hello', \Demo\DemoBundle::$booted_with, 'Bundles are booted with the container.' );

		self::assertInstanceOf( \App\Foo::class, $container->get( \App\Bar::class )->foo, 'Singly implemented interfaces are aliased.' );
		self::assertCount( 1, $container->get( \App\Collector::class )->foos, '_instanceof tags are applied.' );

		self::assertTrue( $container->has( \Configurator\Baz::class ), 'PHP configurator instanceof() conditionals are applied.' );
		self::assertInstanceOf( \Configurator\Baz::class, $container->get( \Configurator\Qux::class )->baz, 'PHP configurator singly implemented interfaces are aliased.' );

		self::assertSame( $container, $GLOBALS['test_actions']['achttienvijftien/container_booted'][0][0] ?? null );
		self::assertSame( $container, apply_filters( 'achttienvijftien/container', null ) );
	}

	public function test_production_serves_cached_container_without_freshness_check(): void {
		self::assertSame( 'production', $this->boot( 'production' )->getParameter( 'env_param' ) );

		$this->write_env_param( 'production', 'changed' );

		self::assertSame( 'production', $this->boot( 'production' )->getParameter( 'env_param' ) );
	}

	public function test_debug_rebuilds_container_when_config_changes(): void {
		self::assertSame( 'local', $this->boot( 'local' )->getParameter( 'env_param' ) );

		$this->write_env_param( 'local', 'changed' );

		self::assertSame( 'changed', $this->boot( 'local' )->getParameter( 'env_param' ) );
	}

	public function test_autoload_skips_boot_when_filter_returns_false(): void {
		$hook = $GLOBALS['wp_filter']['muplugins_loaded'][1][0] ?? null;

		self::assertSame( 0, $hook['accepted_args'] ?? null );

		add_filter( 'achttienvijftien/container_boot_onload', fn() => false );
		$hook['function']();

		self::assertFalse( class_exists( ServiceContainer::class, false ) );
	}

	/**
	 * Boots a fresh kernel for the given environment and returns its container.
	 *
	 * @param string $environment WordPress environment type.
	 *
	 * @return ContainerInterface
	 */
	private function boot( string $environment ): ContainerInterface {
		$GLOBALS['wp_environment_type'] = $environment;

		$kernel = new \TestKernel();
		$kernel->boot();

		return $kernel->get();
	}

	/**
	 * Rewrites the env_param parameter with a future mtime so freshness checks notice it.
	 *
	 * @param string $environment WordPress environment type.
	 * @param string $value       New value.
	 */
	private function write_env_param( string $environment, string $value ): void {
		$file = "$this->project_dir/config/parameters/$environment.yaml";

		file_put_contents( $file, "parameters:\n  env_param: $value\n" );
		touch( $file, time() + 10 );
	}
}
