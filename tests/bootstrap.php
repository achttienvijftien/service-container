<?php
/**
 * PHPUnit bootstrap: composer autoload + minimal WordPress stubs.
 *
 * @package AchttienVijftien\ServiceContainer\Test
 */

require_once dirname( __DIR__ ) . '/vendor/autoload.php';
require_once dirname( __DIR__ ) . '/service-container.php';
require_once __DIR__ . '/FixtureServiceContainer.php';

$GLOBALS['__wp_env_type'] = 'production';

if ( ! function_exists( 'wp_get_environment_type' ) ) {
	/**
	 * Returns the stubbed WordPress environment type.
	 *
	 * @return string
	 */
	function wp_get_environment_type(): string {
		return $GLOBALS['__wp_env_type'];
	}
}

if ( ! function_exists( 'apply_filters' ) ) {
	/**
	 * Returns the value unchanged.
	 *
	 * @param string $hook  The filter hook.
	 * @param mixed  $value The value being filtered.
	 *
	 * @return mixed
	 */
	function apply_filters( string $hook, mixed $value ): mixed {
		return $value;
	}
}

if ( ! function_exists( 'do_action' ) ) {
	/**
	 * No-op action dispatch stub.
	 *
	 * @return void
	 */
	function do_action(): void {
	}
}

if ( ! function_exists( 'add_filter' ) ) {
	/**
	 * No-op filter registration stub.
	 *
	 * @return bool
	 */
	function add_filter(): bool {
		return true;
	}
}

if ( ! function_exists( 'wp_mkdir_p' ) ) {
	/**
	 * Recursively creates a directory.
	 *
	 * @param string $target The directory to create.
	 *
	 * @return bool
	 */
	function wp_mkdir_p( string $target ): bool {
		return is_dir( $target ) || mkdir( $target, 0777, true );
	}
}

if ( ! function_exists( 'wp_die' ) ) {
	/**
	 * Throws instead of dying so tests can assert on it.
	 *
	 * @param string $message The death message.
	 *
	 * @throws RuntimeException Always.
	 */
	function wp_die( string $message = '' ): never {
		throw new RuntimeException( 'wp_die: ' . $message );
	}
}

if ( ! function_exists( 'esc_html' ) ) {
	/**
	 * Escapes HTML entities.
	 *
	 * @param string $text The text to escape.
	 *
	 * @return string
	 */
	function esc_html( string $text ): string {
		return htmlspecialchars( $text, ENT_QUOTES );
	}
}
