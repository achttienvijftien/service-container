<?php

namespace Demo;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class DemoBundle extends Bundle {

	public static ?string $booted_with = null;

	public function build( ContainerBuilder $container ): void {
		$GLOBALS['bundle_build_order'][] = $this->getName();
		$container->setParameter( 'demo.built', true );
	}

	public function boot(): void {
		$GLOBALS['bundle_boot_order'][] = $this->getName();
		self::$booted_with              = $this->container->getParameter( 'demo.greeting' );
	}
}
