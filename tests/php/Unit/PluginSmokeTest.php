<?php
declare( strict_types=1 );

namespace CoreLabs\ProductOptions\Tests\Unit;

class PluginSmokeTest extends TestCase {

	public function test_instance_is_singleton(): void {
		$a = \CoreLabs\ProductOptions\Plugin::instance();
		$b = \CoreLabs\ProductOptions\Plugin::instance();
		$this->assertSame( $a, $b );
	}

	public function test_boot_is_idempotent(): void {
		$plugin = \CoreLabs\ProductOptions\Plugin::instance();
		$plugin->boot();
		$plugin->boot();
		$this->assertTrue( true ); // No exception/redeclare on second boot.
	}
}
