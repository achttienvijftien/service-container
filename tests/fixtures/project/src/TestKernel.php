<?php
/**
 * Kernel inside the fixture project, so get_project_dir() resolves here.
 *
 * @package AchttienVijftien\ServiceContainer\Tests
 */

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

/**
 * Class TestKernel.
 */
class TestKernel extends AchttienVijftien\ServiceContainer\ServiceContainer {

	/**
	 * Also registers services through the PHP configurator, which relies on Symfony loader internals.
	 *
	 * @param ContainerConfigurator $container Container configurator.
	 *
	 * @return void
	 */
	protected function configure_container( ContainerConfigurator $container ): void {
		parent::configure_container( $container );

		$services = $container->services()->defaults()->autowire();
		$services->instanceof( Configurator\BazInterface::class )->public();
		$services->load( 'Configurator\\', __DIR__ . '/Configurator/' );
		$services->get( Configurator\Qux::class )->public();
	}
}
