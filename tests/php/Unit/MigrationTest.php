<?php
declare( strict_types=1 );

namespace CoreLabs\ProductOptions\Tests\Unit;

use CoreLabs\ProductOptions\Setup\Migration;

final class MigrationTest extends TestCase {

	public function test_plan_builds_product_assigned_groups(): void {
		$rows = array(
			array(
				'product_id'    => 15,
				'product_title' => 'Forge Hoodie',
				'fields_json'   => '{"fields":[{"id":"note","type":"text","label":"Note"}]}',
			),
		);
		$plan = Migration::plan( $rows );
		$this->assertCount( 1, $plan );
		$this->assertSame( 'Forge Hoodie — Options', $plan[0]['title'] );
		$this->assertSame( array( 'mode' => 'products', 'ids' => array( 15 ) ), $plan[0]['assignment'] );
		$this->assertSame( 10, $plan[0]['priority'] );
		$this->assertSame( 15, $plan[0]['product_id'] );
		$this->assertSame( 'note', $plan[0]['fields'][0]['id'] );
	}

	public function test_plan_skips_invalid_or_empty_json(): void {
		$rows = array(
			array( 'product_id' => 1, 'product_title' => 'A', 'fields_json' => 'not-json{' ),
			array( 'product_id' => 2, 'product_title' => 'B', 'fields_json' => '' ),
			array( 'product_id' => 3, 'product_title' => 'C', 'fields_json' => '{"fields":[]}' ),
			array( 'product_id' => 4, 'product_title' => 'D', 'fields_json' => '{"fields":[{"id":"x","type":"text"}]}' ),
		);
		$plan = Migration::plan( $rows );
		$this->assertCount( 1, $plan );
		$this->assertSame( 4, $plan[0]['product_id'] );
	}
}
