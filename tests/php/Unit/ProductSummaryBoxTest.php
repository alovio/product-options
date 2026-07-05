<?php
declare( strict_types=1 );

namespace CoreLabs\ProductOptions\Tests\Unit;

use CoreLabs\ProductOptions\Admin\ProductSummaryBox;
use Brain\Monkey;

final class ProductSummaryBoxTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\Functions\when( 'admin_url' )->alias(
			static function ( $path ) {
				return 'https://x.test/wp-admin/' . $path;
			}
		);
	}

	public function test_items_maps_groups_to_rows_with_edit_urls(): void {
		$groups = array(
			array( 'id' => 7, 'title' => 'Gift options', 'status' => 'publish', 'fields' => array( array( 'id' => 'a' ), array( 'id' => 'b' ) ), 'assignment' => array( 'mode' => 'all', 'ids' => array() ), 'priority' => 10 ),
		);
		$items = ProductSummaryBox::items( $groups );
		$this->assertCount( 1, $items );
		$this->assertSame( 7, $items[0]['id'] );
		$this->assertSame( 'Gift options', $items[0]['title'] );
		$this->assertSame( 2, $items[0]['field_count'] );
		$this->assertSame( 'https://x.test/wp-admin/admin.php?page=alovio-product-options#/groups/7', $items[0]['edit_url'] );
	}

	public function test_items_empty_input(): void {
		$this->assertSame( array(), ProductSummaryBox::items( array() ) );
	}
}
