<?php
declare( strict_types=1 );

namespace CoreLabs\ProductOptions\Tests\Unit;

use CoreLabs\ProductOptions\Groups\GroupResolver;

final class GroupResolverTest extends TestCase {

	private function groups(): array {
		return array(
			array( 'id' => 1, 'title' => 'B', 'status' => 'publish', 'fields' => array(), 'assignment' => array( 'mode' => 'all', 'ids' => array() ), 'priority' => 10 ),
			array( 'id' => 2, 'title' => 'A', 'status' => 'publish', 'fields' => array(), 'assignment' => array( 'mode' => 'categories', 'ids' => array( 5 ) ), 'priority' => 10 ),
			array( 'id' => 3, 'title' => 'C', 'status' => 'publish', 'fields' => array(), 'assignment' => array( 'mode' => 'products', 'ids' => array( 77 ) ), 'priority' => 1 ),
			array( 'id' => 4, 'title' => 'D', 'status' => 'draft', 'fields' => array(), 'assignment' => array( 'mode' => 'all', 'ids' => array() ), 'priority' => 1 ),
		);
	}

	public function test_all_mode_matches_everything(): void {
		$r = GroupResolver::filter_groups( $this->groups(), 999, array() );
		$this->assertSame( array( 1 ), array_column( $r, 'id' ) );
	}

	public function test_category_match_includes_ancestor_ids_passed_in(): void {
		$r = GroupResolver::filter_groups( $this->groups(), 999, array( 5, 9 ) );
		$this->assertSame( array( 2, 1 ), array_column( $r, 'id' ) ); // priority tie -> title A before B
	}

	public function test_product_match_and_priority_order(): void {
		$r = GroupResolver::filter_groups( $this->groups(), 77, array() );
		$this->assertSame( array( 3, 1 ), array_column( $r, 'id' ) ); // priority 1 before 10
	}

	public function test_draft_never_matches(): void {
		$r = GroupResolver::filter_groups( $this->groups(), 77, array( 5 ) );
		$this->assertNotContains( 4, array_column( $r, 'id' ) );
	}
}
