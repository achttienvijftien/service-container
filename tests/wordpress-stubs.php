<?php
/**
 * Stubs for the WordPress functions the package calls besides the hook API, loaded only when WordPress itself is not.
 *
 * @package AchttienVijftien\ServiceContainer\Tests
 */

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
