<?php

namespace Demo;

use Symfony\Component\HttpKernel\Bundle\Bundle;

class DemoBundle extends Bundle {

	public static ?string $booted_with = null;

	public function boot(): void {
		self::$booted_with = $this->container->getParameter( 'demo.greeting' );
	}
}
