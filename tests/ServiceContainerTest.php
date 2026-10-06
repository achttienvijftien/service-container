<?php
/**
 * Integration tests booting the container against a fixture project.
 *
 * @package AchttienVijftien\ServiceContainer\Tests
 */

namespace AchttienVijftien\ServiceContainer\Tests;

use AchttienVijftien\ServiceContainer\ServiceContainer;
use PHPUnit\Framework\Attributes\DataProvider;
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
		add_action(
			'achttienvijftien/container_booted',
			function ( ContainerInterface $container ): void {
				$GLOBALS['booted_container'] = $container;
			}
		);

		$container = $this->boot( 'local' );

		self::assertSame( $this->project_dir, $container->getParameter( 'kernel.project_dir' ) );
		self::assertSame( 'local', $container->getParameter( 'kernel.environment' ) );
		self::assertTrue( $container->getParameter( 'kernel.debug' ) );
		self::assertSame( [ 'DemoBundle' => \Demo\DemoBundle::class ], $container->getParameter( 'kernel.bundles' ) );
		self::assertSame(
			[
				'DemoBundle' => [
					'path'      => "$this->project_dir/src/Demo",
					'namespace' => 'Demo',
				],
			],
			$container->getParameter( 'kernel.bundles_metadata' )
		);
		self::assertTrue( $container->getParameter( 'kernel.runtime_mode.cli' ) );

		self::assertSame( 'local', $container->getParameter( 'env_param' ), 'parameters/{env}.yaml is imported.' );
		self::assertSame( 'hello', $container->getParameter( 'demo.greeting' ), 'packages/*.yaml configures bundle extensions.' );
		self::assertTrue( $container->getParameter( 'demo.built' ), 'Bundles build the container.' );
		self::assertSame( 'hello', \Demo\DemoBundle::$booted_with, 'Bundles are booted with the container.' );

		self::assertInstanceOf( \App\Foo::class, $container->get( \App\Bar::class )->foo, 'Singly implemented interfaces are aliased.' );
		self::assertCount( 1, $container->get( \App\Collector::class )->foos, '_instanceof tags are applied.' );

		self::assertTrue( $container->has( \Configurator\Baz::class ), 'PHP configurator instanceof() conditionals are applied.' );
		self::assertInstanceOf( \Configurator\Baz::class, $container->get( \Configurator\Qux::class )->baz, 'PHP configurator singly implemented interfaces are aliased.' );

		self::assertSame( $container, $GLOBALS['booted_container'] ?? null );
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

	public function test_debug_reuses_container_when_config_is_unchanged(): void {
		$this->boot( 'local' );

		// A rebuild would rewrite the cache file and drop this marker.
		$cache_file = "$this->project_dir/var/cache/ServiceContainer.php";
		file_put_contents( $cache_file, "\n// reused\n", FILE_APPEND );

		$this->boot( 'local' );

		self::assertStringEndsWith( "\n// reused\n", file_get_contents( $cache_file ) );
	}

	public function test_rebuilds_container_when_cache_file_is_broken(): void {
		// A truncated write leaves a cache file that no longer parses.
		( new Filesystem() )->dumpFile( "$this->project_dir/var/cache/ServiceContainer.php", "<?php\nreturn new \\Container" );

		self::assertSame( 'production', $this->boot( 'production' )->getParameter( 'env_param' ) );
	}

	#[DataProvider( 'environment_types' )]
	public function test_maps_wordpress_environment_type_to_kernel( string $environment, bool $debug ): void {
		$container = $this->boot( $environment );

		self::assertSame( $environment, $container->getParameter( 'kernel.environment' ) );
		self::assertSame( $debug, $container->getParameter( 'kernel.debug' ) );
	}

	/**
	 * WordPress environment types and whether they enable debug mode.
	 *
	 * @return array<string, array{string, bool}>
	 */
	public static function environment_types(): array {
		return [
			'local'       => [ 'local', true ],
			'development' => [ 'development', true ],
			'staging'     => [ 'staging', false ],
			'production'  => [ 'production', false ],
		];
	}

	public function test_container_bundles_filter_controls_bundle_order(): void {
		add_filter(
			'achttienvijftien/container_bundles',
			function ( array $bundles ): array {
				$GLOBALS['filtered_bundles'] = $bundles;

				return [ \Demo\ExtraBundle::class => [ 'all' => true ] ] + $bundles;
			}
		);

		$container = $this->boot( 'local' );

		self::assertSame( [ \Demo\DemoBundle::class => [ 'all' => true ] ], $GLOBALS['filtered_bundles'], 'The filter receives config/bundles.php.' );
		self::assertSame( [ 'ExtraBundle', 'DemoBundle' ], array_keys( $container->getParameter( 'kernel.bundles' ) ) );
		self::assertSame( [ 'ExtraBundle', 'DemoBundle' ], $GLOBALS['bundle_build_order'] ?? null, 'Bundles build in filtered order.' );
		self::assertSame( [ 'ExtraBundle', 'DemoBundle' ], $GLOBALS['bundle_boot_order'] ?? null, 'Bundles boot in filtered order.' );
	}

	#[DataProvider( 'bundle_environments' )]
	public function test_bundle_registration_respects_environments( array $environments, string $environment, bool $registered ): void {
		add_filter(
			'achttienvijftien/container_bundles',
			fn( array $bundles ): array => $bundles + [ \Demo\ExtraBundle::class => $environments ]
		);

		$bundles = $this->boot( $environment )->getParameter( 'kernel.bundles' );

		self::assertSame( $registered, isset( $bundles['ExtraBundle'] ) );
	}

	/**
	 * Bundle environment flags, the environment booted and whether the bundle should register.
	 *
	 * @return array<string, array{array<string, bool>, string, bool}>
	 */
	public static function bundle_environments(): array {
		return [
			'all environments'            => [ [ 'all' => true ], 'local', true ],
			'other environment only'      => [ [ 'production' => true ], 'local', false ],
			'own environment only'        => [ [ 'production' => true ], 'production', true ],
			'environment overrides "all"' => [ [ 'all' => true, 'local' => false ], 'local', false ],
		];
	}

	public function test_container_booted_fires_after_bundles_boot(): void {
		add_action(
			'achttienvijftien/container_booted',
			function (): void {
				$GLOBALS['greeting_when_booted'] = \Demo\DemoBundle::$booted_with;
			}
		);

		$this->boot( 'local' );

		self::assertSame( 'hello', $GLOBALS['greeting_when_booted'] ?? null );
	}

	#[DataProvider( 'boot_failures' )]
	public function test_boot_failure_handling( string $environment, string $exception_class, string $message ): void {
		add_filter(
			'achttienvijftien/container_bundles',
			fn( array $bundles ): array => $bundles + [ \Other\DemoBundle::class => [ 'all' => true ] ]
		);

		try {
			$this->boot( $environment );
		} catch ( \Exception $exception ) {
			self::assertSame( [ $exception_class, $message ], [ $exception::class, $exception->getMessage() ] );

			return;
		}

		self::fail( 'Booting with duplicate bundle names should fail.' );
	}

	/**
	 * Environments and the exception class and message a duplicate bundle name should surface as.
	 *
	 * @return array<string, array{string, class-string, string}>
	 */
	public static function boot_failures(): array {
		return [
			'local rethrows'          => [ 'local', \Exception::class, "Bundle 'DemoBundle' already exists" ],
			'production calls wp_die' => [ 'production', \WpDieException::class, 'Could not boot container: Bundle &#039;DemoBundle&#039; already exists' ],
		];
	}

	public function test_autoload_skips_boot_when_filter_returns_false(): void {
		$callbacks = $GLOBALS['wp_filter']['muplugins_loaded']->callbacks[1] ?? [];

		self::assertCount( 1, $callbacks, 'WordPress converted the pre-registered hook.' );
		self::assertSame( 0, reset( $callbacks )['accepted_args'] );

		add_filter( 'achttienvijftien/container_boot_onload', fn() => false );
		do_action( 'muplugins_loaded' );

		self::assertFalse( class_exists( ServiceContainer::class, false ) );
	}

	public function test_autoloader_loads_service_container_class(): void {
		self::assertTrue( class_exists( ServiceContainer::class ) );
		self::assertSame(
			realpath( dirname( __DIR__ ) . '/service-container.php' ),
			realpath( ( new \ReflectionClass( ServiceContainer::class ) )->getFileName() )
		);
	}

	public function test_autoload_boots_vendor_installed_container_on_muplugins_loaded(): void {
		$package_dir = "$this->project_dir/vendor/achttienvijftien/service-container";

		// Load the class from a project's vendor dir so project dir discovery runs as in a real install.
		foreach ( [ 'composer.json', 'service-container.php' ] as $file ) {
			( new Filesystem() )->copy( dirname( __DIR__ ) . "/$file", "$package_dir/$file" );
		}
		require "$package_dir/service-container.php";

		// Guard: booting the repository's class would resolve and write to whatever project surrounds this checkout.
		self::assertSame( "$package_dir/service-container.php", ( new \ReflectionClass( ServiceContainer::class ) )->getFileName() );

		$GLOBALS['wp_environment_type'] = 'production';
		do_action( 'muplugins_loaded' );

		$container = apply_filters( 'achttienvijftien/container', null );

		self::assertInstanceOf( ContainerInterface::class, $container );
		self::assertSame( $this->project_dir, $container->getParameter( 'kernel.project_dir' ) );
		self::assertSame( "$this->project_dir/var/cache", $container->getParameter( 'kernel.cache_dir' ) );
		self::assertSame( "$this->project_dir/var/log", $container->getParameter( 'kernel.logs_dir' ) );
		self::assertFileExists( "$this->project_dir/var/cache/ServiceContainer.php" );
		self::assertSame( 'production', $container->getParameter( 'env_param' ) );
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
