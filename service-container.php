<?php
/**
 * Service Container
 *
 * Plugin Name: Service Container
 * Description: Provides a Symfony DI container for WordPress.
 * Version: 1.0.0
 *
 * @package AchttienVijftien\ServiceContainer
 *
 * @phpcs:disable WordPress.WP.AlternativeFunctions
 */

namespace AchttienVijftien\ServiceContainer;

use Symfony\Component\Config\ConfigCache;
use Symfony\Component\Config\Exception\FileLoaderImportCircularReferenceException;
use Symfony\Component\Config\Exception\LoaderLoadException;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Dumper\PhpDumper;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;
use Symfony\Component\HttpKernel\DependencyInjection\MergeExtensionConfigurationPass;

/**
 * Class ServiceContainer.
 */
class ServiceContainer {

	private const CONTAINER_CLASS = 'ServiceContainer';

	/**
	 * Path to configuration directory.
	 *
	 * @var string
	 */
	private string $config_path;

	/**
	 * Is debug env?
	 *
	 * @var bool
	 */
	private bool $debug;

	/**
	 * The container.
	 *
	 * @var ContainerInterface
	 */
	private ContainerInterface $container;

	/**
	 * Bundles.
	 *
	 * @var BundleInterface[]
	 */
	private array $bundles = [];

	/**
	 * Environment name, either 'local', 'development', 'staging' or 'production'.
	 *
	 * @var string
	 */
	private string $environment;

	/**
	 * Project directory.
	 *
	 * @var string|null
	 */
	private ?string $project_dir = null;

	/**
	 * SerivceContainer constructor.
	 */
	public function __construct() {
		$this->environment = $this->get_environment();
		$this->debug       = \in_array( $this->environment, [ 'local', 'development' ], true );
		$this->config_path = $this->get_project_dir() . '/config';
	}

	/**
	 * Returns the environment, mapping the WP environment to the known Symfony envs.
	 *
	 * @return string
	 */
	private function get_environment(): string {
		return match ( WP_ENV ) {
			'development' => 'dev',
			default => WP_ENV
		};
	}

	/**
	 * Boots the service container.
	 *
	 * @return void
	 * @throws \Exception On container boot error if env type is development.
	 */
	public function boot(): void {
		try {
			$this->initialize_bundles();
			$this->initialize_container();

			add_filter( 'achttienvijftien/container', [ $this, 'get' ] );
			do_action( 'achttienvijftien/container_booted', $this->get() );
		} catch ( \Exception $exception ) {
			if ( 'local' === wp_get_environment_type() ) {
				throw $exception;
			}
			wp_die( esc_html( 'Could not boot container: ' . $exception->getMessage() ) );
		}
	}

	/**
	 * Initialize the container.
	 *
	 * @return void
	 * @throws \Exception On initialization errors.
	 *
	 * @phpcs:disable WordPress.PHP.DiscouragedPHPFunctions.runtime_configuration_error_reporting
	 * @phpcs:disable WordPress.PHP.DevelopmentFunctions.prevent_path_disclosure_error_reporting
	 */
	protected function initialize_container(): void {
		$cache = new ConfigCache(
			$this->get_cache_dir() . '/' . self::CONTAINER_CLASS . '.php',
			$this->debug
		);

		$cached_container     = null;
		$old_container        = null;
		$container_cache_path = $cache->getPath();
		$container_cache_file = basename( $container_cache_path );

		$error_level = error_reporting( \E_ALL ^ \E_WARNING );

		try {
			if ( is_file( $container_cache_path ) ) {
				$cached_container = include $container_cache_path;

				if ( \is_object( $cached_container ) && ( ! $this->debug || $cache->isFresh() ) ) {
					$this->container = $cached_container;

					return;
				}
			}
			// phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
		} catch ( \Throwable ) {
			// Fall through on error loading cached container to build a fresh one.
		} finally {
			error_reporting( $error_level );
		}

		if ( \is_object( $cached_container ) ) {
			$old_container = $cached_container;
		}

		try {
			$container = $this->build_container();
			$this->configure_container( $container );
			$container->compile();

			$dumper  = new PhpDumper( $container );
			$content = $dumper->dump(
				[
					'class'    => self::CONTAINER_CLASS,
					'as_files' => true,
					'debug'    => $this->debug,
				]
			);

			$container_code = $content[ $container_cache_file ];
			unset( $content[ $container_cache_file ] );

			$fs = new Filesystem();

			foreach ( $content as $file => $code ) {
				$fs->dumpFile( "{$this->get_cache_dir()}/$file", $code );
				// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
				@chmod( "{$this->get_cache_dir()}/$file", 0666 & ~umask() );
			}

			$cache->write( $container_code, $container->getResources() );

			$this->container = require $container_cache_path;

			if ( $old_container ) {
				$old_container_reflection = new \ReflectionClass( $old_container );

				$old_container_class = $old_container_reflection->name;
				$old_container_dir   = \dirname( $old_container_reflection->getFileName() );

				if ( \get_class( $this->container ) !== $old_container_class ) {
					$legacy_dirs = glob( $this->get_cache_dir() . '/Container*.legacy', GLOB_NOSORT );

					foreach ( $legacy_dirs as $legacy_dir ) {
						// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
						if ( $legacy_dir !== $old_container_dir && @unlink( $legacy_dir ) ) {
							$fs->remove(
								\dirname( $legacy_dir ) . '/' . basename( $legacy_dir, '.legacy' )
							);
						}
					}

					touch( "$old_container_dir.legacy" );
				}
			}
		} finally {
			error_reporting( $error_level );
		}
	}


	/**
	 * Gets the application root dir (path of the project's composer file).
	 *
	 * @return string The project root dir
	 */
	private function get_project_dir(): string {
		if ( null === $this->project_dir ) {
			$reflection_object = new \ReflectionObject( $this );

			$container_dir = \dirname( $reflection_object->getFileName(), 2 );

			$composer_dir = null;
			$dir          = $container_dir;
			$prev_dir     = null;

			while ( $prev_dir !== $dir ) {
				if ( is_file( $dir . '/composer.json' ) ) {
					$composer_dir = $dir;
					break;
				}

				$prev_dir = $dir;
				$dir      = \dirname( $dir );
			}

			$this->project_dir = $composer_dir ?: $container_dir;
		}

		return $this->project_dir;
	}

	/**
	 * Returns the compailed container.
	 *
	 * @return ContainerInterface
	 */
	public function get(): ContainerInterface {
		return $this->container;
	}

	/**
	 * Entry point.
	 *
	 * @return void
	 */
	public static function run(): void {
		$container = new self();
		$container->boot();
	}

	/**
	 * Build the container.
	 *
	 * @return ContainerBuilder
	 * @throws \RuntimeException If cache directory could not be created or written to.
	 */
	protected function build_container(): ContainerBuilder {
		if ( ! is_dir( $this->get_cache_dir() ) ) {
			if ( false === wp_mkdir_p( $this->get_cache_dir() ) ) {
				throw new \RuntimeException(
					"Unable to create the cache directory ({$this->get_cache_dir()})."
				);
			}
		} elseif ( ! is_writable( $this->get_cache_dir() ) ) {
			throw new \RuntimeException(
				"Unable to write to the cache directory ({$this->get_cache_dir()})."
			);
		}

		$builder = new ContainerBuilder();
		$builder->addObjectResource( $this );

		$bundles          = [];
		$bundles_metadata = [];

		foreach ( $this->bundles as $bundle ) {
			$bundles[ $bundle->getName() ]          = $bundle::class;
			$bundles_metadata[ $bundle->getName() ] = [
				'path'      => $bundle->getPath(),
				'namespace' => $bundle->getNamespace(),
			];
		}

		$builder->getParameterBag()->add(
			[
				'kernel.project_dir'         => $this->get_project_dir(),
				'kernel.environment'         => $this->environment,
				'kernel.runtime_environment' => $this->environment,
				'kernel.debug'               => $this->debug,
				'kernel.build_dir'           => $this->get_cache_dir(),
				'kernel.cache_dir'           => $this->get_cache_dir(),
				'kernel.logs_dir'            => $this->get_log_dir(),
				'kernel.bundles'             => $bundles,
				'kernel.bundles_metadata'    => $bundles_metadata,
				'kernel.charset'             => 'UTF-8',
				'kernel.container_class'     => self::CONTAINER_CLASS,
			]
		);

		foreach ( $this->bundles as $bundle ) {
			$extension = $bundle->getContainerExtension();
			if ( $extension ) {
				$builder->registerExtension( $extension );
			}

			if ( $this->debug ) {
				$builder->addObjectResource( $bundle );
			}

			$bundle->build( $builder );
		}

		$extensions = array_map(
			fn( $extension ) => $extension->getAlias(),
			$builder->getExtensions()
		);

		$builder->getCompilerPassConfig()->setMergePass(
			new MergeExtensionConfigurationPass( $extensions )
		);

		return $builder;
	}

	/**
	 * Configure container.
	 *
	 * @param ContainerBuilder $container Container.
	 *
	 * @return void
	 * @throws \Exception On configuration loader exceptions.
	 */
	protected function configure_container( ContainerBuilder $container ): void {
		try {
			$loader = new YamlFileLoader( $container, new FileLocator( $this->config_path ) );
			$loader->import( 'services.yaml', null, 'not_found' );
			$loader->import( "parameters/$this->environment.yaml", null, 'not_found' );
			$loader->import( 'packages/*.yaml', null, true );

			$container->fileExists( "$this->config_path/bundles.php" );
		} catch ( FileLoaderImportCircularReferenceException | LoaderLoadException $e ) {
			throw new \Exception( 'Could not configure container: ' . $e->getMessage(), null, $e );
		}
	}

	/**
	 * Returns the registered bundles, either from config/bundles.php or through the
	 * achttienvijftien/bundles filter.
	 *
	 * @return array
	 */
	protected function get_registered_bundles(): array {
		$config_bundles_path = $this->get_project_dir() . '/config/bundles.php';

		$bundles = [];

		if ( is_file( $config_bundles_path ) ) {
			$bundles = require $config_bundles_path;
		}

		$bundles = apply_filters( 'achttienvijftien/container_bundles', $bundles );

		$registered_bundles = [];

		foreach ( $bundles as $class => $environments ) {
			if ( $environments[ $this->environment ] ?? $environments['all'] ?? false ) {
				$registered_bundles[] = new $class();
			}
		}

		return $registered_bundles;
	}

	/**
	 * Initializes bundles.
	 *
	 * @throws \Exception If two bundles with the same name were found.
	 */
	protected function initialize_bundles(): void {
		$this->bundles = [];

		foreach ( $this->get_registered_bundles() as $bundle ) {
			$name = $bundle->getName();
			if ( isset( $this->bundles[ $name ] ) ) {
				throw new \Exception( "Bundle '$name' already exists" );
			}
			$this->bundles[ $name ] = $bundle;
		}
	}

	/**
	 * Returns cache directory for compiled container.
	 *
	 * @return string
	 */
	private function get_cache_dir(): string {
		return $this->get_project_dir() . '/var/cache';
	}

	/**
	 * Returns the log dir.
	 *
	 * @return string
	 */
	private function get_log_dir(): string {
		return $this->get_project_dir() . '/var/log';
	}
}

add_action( 'plugins_loaded', fn() => ServiceContainer::run() );
