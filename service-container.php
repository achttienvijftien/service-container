<?php
/**
 * Service Container
 *
 * Plugin Name: Service Container
 * Description: Provides a Symfony DI container for WordPress.
 * Version: 1.2.0
 *
 * @package AchttienVijftien\ServiceContainer
 *
 * @phpcs:disable WordPress.WP.AlternativeFunctions
 * @phpcs:disable Squiz.Commenting.FunctionCommentThrowTag.WrongNumber
 */

namespace AchttienVijftien\ServiceContainer;

use Symfony\Component\Config\ConfigCache;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Config\Loader\DelegatingLoader;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\Config\Loader\LoaderResolver;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Dumper\PhpDumper;
use Symfony\Component\DependencyInjection\Loader\ClosureLoader;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Loader\GlobFileLoader;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
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
	 * @var ContainerInterface|null
	 */
	private ?ContainerInterface $container = null;

	/**
	 * Bundles.
	 *
	 * @var BundleInterface[]
	 */
	private array $bundles = [];

	/**
	 * Environment name (follows WordPress environment types); either 'local', 'development', 'staging' or 'production'.
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
	 * ServiceContainer constructor.
	 */
	public function __construct() {
		$this->environment = wp_get_environment_type();
		$this->debug       = in_array( $this->environment, [ 'local', 'development' ], true );
		$this->config_path = $this->get_config_path();
	}

	/**
	 * Returns the configuration directory, overridable as a test seam.
	 *
	 * @return string
	 */
	protected function get_config_path(): string {
		return $this->get_project_dir() . '/config';
	}

	/**
	 * Clone magic method.
	 *
	 * @return void
	 */
	public function __clone() {
		$this->container = null;
	}

	/**
	 * Initializes bundles and container.
	 *
	 * @return void
	 * @throws \Exception When bundles or container couldn't be booted, only on local environments.
	 */
	private function pre_boot(): void {
		try {
			$this->initialize_bundles();
			$this->initialize_container();

			add_filter( 'achttienvijftien/container', [ $this, 'get' ] );

			return;
		} catch ( \Exception $exception ) {
			if ( 'local' === wp_get_environment_type() ) {
				throw $exception;
			}
			wp_die( esc_html( 'Could not boot container: ' . $exception->getMessage() ) );
		}
	}

	/**
	 * Boots the service container.
	 *
	 * @return void
	 * @throws \Exception On container boot error if env type is development.
	 */
	public function boot(): void {
		if ( null === $this->container ) {
			$this->pre_boot();
		}

		foreach ( $this->get_bundles() as $bundle ) {
			$bundle->setContainer( $this->container );
			$bundle->boot();
		}

		do_action( 'achttienvijftien/container_booted', $this->get() );
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
	 * Returns the compiled container.
	 *
	 * @return ContainerInterface|null
	 */
	public function get(): ?ContainerInterface {
		return $this->container;
	}

	/**
	 * Entry point.
	 *
	 * @return void
	 * @throws \Exception If container could not be booted.
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
	 * @throws \Exception If something went wrong with the loader.
	 */
	protected function build_container(): ContainerBuilder {
		if ( ! is_dir( $this->get_cache_dir() ) ) {
			if ( false === wp_mkdir_p( $this->get_cache_dir() ) ) {
				throw new \RuntimeException(
				// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
					"Unable to create the cache directory ({$this->get_cache_dir()})."
				);
			}
		} elseif ( ! is_writable( $this->get_cache_dir() ) ) {
			throw new \RuntimeException(
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
				"Unable to write to the cache directory ({$this->get_cache_dir()})."
			);
		}

		$builder = new ContainerBuilder();
		$builder->addObjectResource( $this );

		$bundles          = [];
		$bundles_metadata = [];

		foreach ( $this->get_bundles() as $bundle ) {
			$bundles[ $bundle->getName() ]          = $bundle::class;
			$bundles_metadata[ $bundle->getName() ] = [
				'path'      => $bundle->getPath(),
				'namespace' => $bundle->getNamespace(),
			];
		}

		$builder->getParameterBag()->add(
			[
				'kernel.project_dir'         => $this->get_project_dir(),
				// The WordPress name: bundle extensions derive their own
				// loaders' env and ContainerConfigurator::env() from this
				// parameter (ExtensionTrait), and existing bundles import
				// WP-named parameter files with it. Project-level config
				// runs on the Symfony channel instead (see
				// get_container_loader()); code needing the channel calls
				// self::environment_channel().
				'kernel.environment'         => $this->environment,
				'kernel.runtime_environment' => $this->environment,
				'kernel.runtime_mode'        => '%env(query_string:default:container.runtime_mode:APP_RUNTIME_MODE)%',
				'kernel.runtime_mode.web'    => '%env(bool:default::key:web:default:kernel.runtime_mode:)%',
				'kernel.runtime_mode.cli'    => '%env(not:default:kernel.runtime_mode.web:)%',
				'kernel.runtime_mode.worker' => '%env(bool:default::key:worker:default:kernel.runtime_mode:)%',
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

		foreach ( $this->get_bundles() as $bundle ) {
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

		$this->register_container_configuration( $this->get_container_loader( $builder ) );

		return $builder;
	}

	/**
	 * Registers the container configuration.
	 *
	 * @param LoaderInterface $loader The loader instance responsible for configuring the container.
	 *
	 * @return void
	 * @throws \Exception If something went wrong with the loader.
	 *
	 * @phpcs:disable Generic.Commenting.DocComment.MissingShort
	 */
	public function register_container_configuration( LoaderInterface $loader ): void {
		$loader->load(
			function ( ContainerBuilder $container ) use ( $loader ) {
				$container->addObjectResource( $this );
				$container->fileExists( "$this->config_path/bundles.php" );

				$file = ( new \ReflectionObject( $this ) )->getFileName();
				/** @var PhpFileLoader $kernel_loader */
				$kernel_loader = $loader->getResolver()->resolve( $file );
				$kernel_loader->setCurrentDir( \dirname( $file ) );
				/** @noinspection PhpPassByRefInspection */
				$instanceof = &\Closure::bind( fn &() => $this->instanceof, $kernel_loader, $kernel_loader )();

				try {
					$container_configurator = new ContainerConfigurator(
						container: $container,
						loader: $kernel_loader,
						instanceof: $instanceof,
						path: $file,
						file: $file,
						env: self::environment_channel( $this->environment )
					);
					$this->configure_container( $container_configurator );
				} finally {
					$instanceof = [];
					$kernel_loader->registerAliasesForSinglyImplementedInterfaces();
				}
			}
		);
	}

	/**
	 * Configure container.
	 *
	 * @param ContainerConfigurator $container Container configurator.
	 *
	 * @return void
	 */
	protected function configure_container( ContainerConfigurator $container ): void {
		if ( ! is_file( "$this->config_path/services.yaml" ) ) {
			return;
		}

		$container->import( "$this->config_path/services.yaml", null, 'not_found' );
		$container->import( "$this->config_path/{parameters}/$this->environment.yaml", null, 'not_found' );
		$container->import( "$this->config_path/{packages}/*.yaml", null, 'not_found' );
	}

	/**
	 * Symfony-style channel for a WordPress environment type, so recipes
	 * and config written for Symfony's dev/prod vocabulary apply.
	 *
	 * Only dev and prod by design: the input domain is WordPress's four
	 * hard-validated environment types (wp_get_environment_type() rejects
	 * anything else), and staging maps to prod per Symfony convention
	 * (prod-like wiring, debug off). Symfony's third channel, test, is
	 * unreachable from an environment type; supporting when@test would
	 * require test-harness detection (e.g. WP_TESTS_DOMAIN), deliberately
	 * deferred until config we actually use needs it.
	 *
	 * @param string $environment The WordPress environment type.
	 *
	 * @return string Either 'dev' or 'prod'.
	 */
	public static function environment_channel( string $environment ): string {
		return in_array( $environment, [ 'local', 'development' ], true ) ? 'dev' : 'prod';
	}

	/**
	 * Whether a bundles.php environments entry enables the bundle: the
	 * WordPress name wins, then the Symfony channel, then 'all'.
	 *
	 * @param array  $environments The bundle's environments map.
	 * @param string $environment  The WordPress environment type.
	 *
	 * @return bool
	 */
	public static function bundle_enabled( array $environments, string $environment ): bool {
		return (bool) (
			$environments[ $environment ]
			?? $environments[ self::environment_channel( $environment ) ]
			?? $environments['all']
			?? false
		);
	}

	/**
	 * Returns the registered bundles, either from config/bundles.php or through the
	 * achttienvijftien/bundles filter.
	 *
	 * @return array
	 */
	private function get_registered_bundles(): array {
		$config_bundles_path = $this->get_project_dir() . '/config/bundles.php';

		$bundles = [];

		if ( is_file( $config_bundles_path ) ) {
			$bundles = require $config_bundles_path;
		}

		$bundles = apply_filters( 'achttienvijftien/container_bundles', $bundles );

		$registered_bundles = [];
		foreach ( $bundles as $class => $environments ) {
			if ( self::bundle_enabled( $environments, $this->environment ) ) {
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
				// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
				throw new \Exception( "Bundle '$name' already exists" );
			}
			$this->bundles[ $name ] = $bundle;
		}
	}

	/**
	 * Gets initialized bundles.
	 *
	 * @return BundleInterface[]|null
	 */
	public function get_bundles(): ?array {
		return $this->bundles;
	}

	/**
	 * Returns cache directory for compiled container.
	 *
	 * @return string
	 */
	protected function get_cache_dir(): string {
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

	/**
	 * Returns a loader for the container.
	 *
	 * @param ContainerBuilder $container The container builder.
	 *
	 * @return DelegatingLoader
	 */
	protected function get_container_loader( ContainerBuilder $container ): DelegatingLoader {
		// Loaders match when@<env> blocks; Flex recipes write Symfony's dev/prod vocabulary, so the channel, not the WordPress name, drives config.
		$env      = self::environment_channel( $this->environment );
		$locator  = new FileLocator( $this->config_path );
		$resolver = new LoaderResolver(
			[
				new YamlFileLoader( $container, $locator, $env ),
				new PhpFileLoader( $container, $locator, $env ),
				new GlobFileLoader( $container, $locator, $env ),
				new ClosureLoader( $container ),
			]
		);

		return new DelegatingLoader( $resolver );
	}
}
