<?php
/**
 * Autoload.
 *
 * @package AchttienVijftien\ServiceContainer
 */

namespace AchttienVijftien\ServiceContainer;

const ACTION = 'muplugins_loaded';
const PRIORITY = 1;

/**
 * Adds hook to run the service container on the muplugins_loaded hook.
 *
 * @return void
 */
function add_hooks(): void {
	global $wp_filter;

	// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- preinitialization of hook
	$wp_filter[ ACTION ][ PRIORITY ] = array_merge(
		$wp_filter[ ACTION ][ PRIORITY ] ?? [],
		[
			[
				'accepted_args' => 0,
				'function'      => static function () {
					if ( apply_filters( 'achttienvijftien/container_boot_onload', true ) ) {
						ServiceContainer::run();
					}
				},
			],
		]
	);
}

/**
 * ServiceContainer class autoloader.
 *
 * @param mixed $class_name Class name to be loaded.
 *
 * @return bool|null
 */
function autoload( mixed $class_name ): ?bool {
	if ( __NAMESPACE__ . '\ServiceContainer' === $class_name ) {
		require __DIR__ . '/service-container.php';

		return true;
	}

	return null;
}

/**
 * Registers the autoloader for the ServiceContainer class.
 *
 * @return void
 */
function register_autoloader(): void {
	spl_autoload_register( __NAMESPACE__ . '\autoload' );
}

namespace\register_autoloader();
namespace\add_hooks();
