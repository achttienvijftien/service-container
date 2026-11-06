<?php
/**
 * Tests that the PHPUnit bootstrap refuses to run inside WordPress instead of fataling on its stubs.
 *
 * @package AchttienVijftien\ServiceContainer\Tests
 */

namespace AchttienVijftien\ServiceContainer\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Class BootstrapTest.
 */
class BootstrapTest extends TestCase {

	#[DataProvider( 'wordpress_contexts' )]
	public function test_refuses_to_run_inside_wordpress( string $setup ): void {
		$process = proc_open(
			[ PHP_BINARY, '-r', $setup . ' require $argv[1];', __DIR__ . '/bootstrap.php' ],
			[
				1 => [ 'pipe', 'w' ],
				2 => [ 'pipe', 'w' ],
			],
			$pipes
		);

		$output = stream_get_contents( $pipes[1] );
		$errors = stream_get_contents( $pipes[2] );
		fclose( $pipes[1] );
		fclose( $pipes[2] );

		self::assertSame( 1, proc_close( $process ), "Output: $output$errors" );
		self::assertStringContainsString( 'must run without WordPress loaded', $errors );
	}

	/**
	 * PHP code that puts the process in a WordPress context before the bootstrap loads.
	 *
	 * @return array<string, array{string}>
	 */
	public static function wordpress_contexts(): array {
		$plugin_php = dirname( __DIR__ ) . '/vendor/roots/wordpress-no-content/wp-includes/plugin.php';

		return [
			'ABSPATH defined'        => [ 'define( "ABSPATH", "/srv/wordpress/" );' ],
			'WordPress hooks loaded' => [ 'error_reporting( E_ALL & ~E_DEPRECATED ); require ' . var_export( $plugin_php, true ) . ';' ],
		];
	}
}
