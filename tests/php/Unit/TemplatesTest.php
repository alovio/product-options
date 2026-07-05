<?php
declare( strict_types=1 );

namespace CoreLabs\ProductOptions\Tests\Unit;

use CoreLabs\ProductOptions\Admin\ImportExport;
use CoreLabs\ProductOptions\Templates\Templates;

/**
 * Every shipped template must unpack through the SAME normalizer imports use,
 * with ZERO warnings — this guards template↔schema drift forever.
 */
final class TemplatesTest extends TestCase {

	public function test_six_templates_ship(): void {
		$this->assertCount( 6, Templates::all() );
	}

	public function test_every_template_unpacks_with_zero_warnings(): void {
		foreach ( Templates::all() as $tpl ) {
			$this->assertNotEmpty( $tpl['name'], $tpl['id'] );
			$this->assertNotEmpty( $tpl['description'], $tpl['id'] );
			$out = ImportExport::unpack( $tpl['package'] );
			$this->assertSame( array(), $out['warnings'], "template {$tpl['id']} must be warning-free" );
			$this->assertCount( 1, $out['groups'], $tpl['id'] );
			$this->assertNotEmpty( $out['groups'][0]['fields'], $tpl['id'] );
		}
	}

	public function test_get_returns_null_for_unknown(): void {
		$this->assertNull( Templates::get( 'nope' ) );
		$this->assertNotNull( Templates::get( 'gift-options' ) );
	}
}
