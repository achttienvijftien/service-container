<?php

namespace Demo\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;

class DemoExtension extends Extension {

	public function load( array $configs, ContainerBuilder $container ): void {
		$container->setParameter( 'demo.greeting', array_merge( ...$configs )['greeting'] ?? null );
	}
}
