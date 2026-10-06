<?php
/**
 * PHPUnit bootstrap: loads the package and stubs the WordPress functions it calls.
 *
 * @package AchttienVijftien\ServiceContainer\Tests
 */

require_once dirname( __DIR__ ) . '/vendor/autoload.php';

/**
 * Returns the environment type set by the test.
 *
 * @return string
 */
function wp_get_environment_type(): string {
	return $GLOBALS['wp_environment_type'] ?? 'production';
}

/**
 * Registers a filter callback.
 *
 * @param string   $hook_name Hook name.
 * @param callable $callback  Callback.
 *
 * @return true
 */
function add_filter( string $hook_name, callable $callback ): bool {
	$GLOBALS['test_filters'][ $hook_name ][] = $callback;

	return true;
}

/**
 * Applies registered filter callbacks to a value.
 *
 * @param string $hook_name Hook name.
 * @param mixed  $value     Value to filter.
 *
 * @return mixed
 */
function apply_filters( string $hook_name, mixed $value ): mixed {
	foreach ( $GLOBALS['test_filters'][ $hook_name ] ?? [] as $callback ) {
		$value = $callback( $value );
	}

	return $value;
}

/**
 * Records a fired action and its arguments.
 *
 * @param string $hook_name Hook name.
 * @param mixed  ...$args   Action arguments.
 *
 * @return void
 */
function do_action( string $hook_name, mixed ...$args ): void {
	$GLOBALS['test_actions'][ $hook_name ][] = $args;
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
 * Returns the text unescaped.
 *
 * @param string $text Text.
 *
 * @return string
 */
function esc_html( string $text ): string {
	return $text;
}

/**
 * Throws instead of dying so boot failures surface as test errors.
 *
 * @param string $message Message.
 *
 * @return never
 * @throws RuntimeException Always.
 */
function wp_die( string $message ): never {
	throw new RuntimeException( $message );
}
