<?php
declare( strict_types=1 );

namespace CoreLabs\ProductOptions\Tests\Unit;

use CoreLabs\ProductOptions\Admin\ImportExport;

final class ImportExportTest extends TestCase {

	private function group(): array {
		return array(
			'id'         => 9,
			'title'      => 'Gift options',
			'status'     => 'publish',
			'fields'     => array( array( 'id' => 'wrap', 'type' => 'checkbox', 'label' => 'Wrap', 'price' => 8, 'priceMode' => 'fixed' ) ),
			'assignment' => array( 'mode' => 'all', 'ids' => array() ),
			'priority'   => 10,
		);
	}

	public function test_package_shape_without_ids(): void {
		$pkg = ImportExport::package( array( $this->group() ) );
		$this->assertSame( '2.0', $pkg['version'] );
		$this->assertCount( 1, $pkg['groups'] );
		$this->assertSame( array( 'title', 'fields', 'assignment', 'priority' ), array_keys( $pkg['groups'][0] ) );
		$this->assertArrayNotHasKey( 'id', $pkg['groups'][0] );
	}

	public function test_unpack_normalizes_and_warns_on_unknown_types(): void {
		$json = array(
			'version' => '2.0',
			'groups'  => array(
				array(
					'title'  => 'Mixed',
					'fields' => array(
						array( 'id' => 'ok', 'type' => 'text', 'label' => 'OK' ),
						array( 'id' => 'bad', 'type' => 'hologram', 'label' => 'Nope' ),
					),
				),
			),
		);
		$out = ImportExport::unpack( $json );
		$this->assertCount( 1, $out['groups'] );
		$this->assertCount( 1, $out['groups'][0]['fields'] ); // hologram dropped
		$this->assertNotEmpty( $out['warnings'] );
		$this->assertStringContainsString( 'hologram', implode( ' ', $out['warnings'] ) );
	}

	public function test_unpack_defaults_missing_keys_and_rejects_garbage(): void {
		$out = ImportExport::unpack( array( 'groups' => array( array( 'fields' => array() ) ) ) );
		$this->assertSame( 'Untitled group', $out['groups'][0]['title'] );
		$this->assertSame( array( 'mode' => 'all', 'ids' => array() ), $out['groups'][0]['assignment'] );

		$bad = ImportExport::unpack( 'not-an-array' );
		$this->assertSame( array(), $bad['groups'] );
		$this->assertNotEmpty( $bad['warnings'] );
	}

	public function test_round_trip_preserves_normalized_content(): void {
		$pkg = ImportExport::package( array( $this->group() ) );
		$out = ImportExport::unpack( $pkg );
		$this->assertSame( array(), $out['warnings'] );
		$this->assertSame( 'Gift options', $out['groups'][0]['title'] );
		$this->assertSame( 'wrap', $out['groups'][0]['fields'][0]['id'] );
		$this->assertSame( 8.0, $out['groups'][0]['fields'][0]['price'] );
	}
}
