<?php
/**
 * PHPUnit bootstrap: loads the package, WordPress's real plugin API and stubs for the other WordPress functions it calls.
 *
 * @package AchttienVijftien\ServiceContainer\Tests
 */

require_once dirname( __DIR__ ) . '/vendor/autoload.php';

// Loaded after the autoloader, like wp-settings.php after wp-config.php, so pre-registered hooks are converted as in WordPress.
// WordPress before 5.9 predates PHP 8.1 return types, so its deprecations are silenced while loading; isolated tests fail on any stderr output.
$error_level = error_reporting( error_reporting() & ~E_DEPRECATED );
require_once dirname( __DIR__ ) . '/vendor/roots/wordpress-no-content/wp-includes/plugin.php';
error_reporting( $error_level );

/**
 * Thrown by the wp_die() stub so tests can tell it apart from other exceptions.
 */
class WpDieException extends RuntimeException {
}

/**
 * Returns the environment type set by the test.
 *
 * @return string
 */
function wp_get_environment_type(): string {
	return $GLOBALS['wp_environment_type'] ?? 'production';
}

/**
 * Creates a directory recursively.
 *
 * @param string $target Directory path.
 *
 * @return bool
 */
function wp_mkdir_p( string $target ): bool {
	return is_dir( $target ) || mkdir( $target, 0777, true );
}

/**
 * Escapes HTML special characters, quotes included, like WordPress does.
 *
 * @param string $text Text.
 *
 * @return string
 */
function esc_html( string $text ): string {
	return htmlspecialchars( $text, ENT_QUOTES );
}

/**
 * Throws instead of dying so boot failures surface as test errors.
 *
 * @param string $message Message.
 *
 * @return never
 * @throws WpDieException Always.
 */
function wp_die( string $message ): never {
	throw new WpDieException( $message );
}
