<?php

global $wp_filter;

$wp_filter['plugins_loaded'][1] = array_merge( $wp_filter['mu_plugins_loaded'][1] ?? [], [
	[
		'accepted_args' => 1,
		'function'      => function () {
			require_once __DIR__ . '/service-container.php';
			\AchttienVijftien\ServiceContainer\ServiceContainer::run();
		},
	],
] );
