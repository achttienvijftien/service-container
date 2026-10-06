<?php

namespace Demo;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class ExtraBundle extends Bundle {

	public function build( ContainerBuilder $container ): void {
		$GLOBALS['bundle_build_order'][] = $this->getName();
	}

	public function boot(): void {
		$GLOBALS['bundle_boot_order'][] = $this->getName();
	}
}
