<?php
/**
 * PHPUnit bootstrap: loads the package, WordPress's real plugin API and stubs for the other WordPress functions it calls.
 *
 * @package AchttienVijftien\ServiceContainer\Tests
 */

// The stubs live in their own file, because PHP declares a file's functions before running it and would fatal on WordPress's own.
if ( defined( 'ABSPATH' ) || function_exists( 'add_filter' ) ) {
	fwrite( STDERR, 'These tests load WordPress\'s hook API and stub other WordPress functions, so they must run without WordPress loaded.' . PHP_EOL );
	exit( 1 );
}

require_once dirname( __DIR__ ) . '/vendor/autoload.php';

// Loaded after the autoloader, like wp-settings.php after wp-config.php, so pre-registered hooks are converted as in WordPress.
// WordPress before 5.9 predates PHP 8.1 return types, so its deprecations are silenced while loading; isolated tests fail on any stderr output.
$error_level = error_reporting( error_reporting() & ~E_DEPRECATED );
require_once dirname( __DIR__ ) . '/vendor/roots/wordpress-no-content/wp-includes/plugin.php';
error_reporting( $error_level );

require_once __DIR__ . '/wordpress-stubs.php';
