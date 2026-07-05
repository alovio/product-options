<?php
declare( strict_types=1 );

namespace CoreLabs\ProductOptions\Tests\Unit;

use CoreLabs\ProductOptions\Cart\FileUploads;

final class FileUploadsCartTest extends TestCase {

	private const DAY = 86400;

	public function test_uncarted_token_expires_after_48h(): void {
		$row = array( 'time' => 1000, 'url' => 'u', 'file' => 'f', 'name' => 'n' );
		$this->assertFalse( FileUploads::is_expired( $row, 1000 + self::DAY ) );
		$this->assertTrue( FileUploads::is_expired( $row, 1000 + 3 * self::DAY ) );
	}

	public function test_carted_token_survives_48h_window(): void {
		$row = FileUploads::mark_carted( array( 'time' => 1000, 'url' => 'u' ), 1000 );
		$this->assertFalse( FileUploads::is_expired( $row, 1000 + 3 * self::DAY ) );
	}

	public function test_carted_token_expires_after_30_days(): void {
		$row = FileUploads::mark_carted( array( 'time' => 1000, 'url' => 'u' ), 1000 );
		$this->assertFalse( FileUploads::is_expired( $row, 1000 + 29 * self::DAY ) );
		$this->assertTrue( FileUploads::is_expired( $row, 1000 + 31 * self::DAY ) );
	}

	public function test_carted_mark_refresh_extends_life(): void {
		$row = FileUploads::mark_carted( array( 'time' => 1000 ), 1000 );
		// 29 days later the cart is still alive -> session hook refreshes the mark.
		$row = FileUploads::mark_carted( $row, 1000 + 29 * self::DAY );
		$this->assertFalse( FileUploads::is_expired( $row, 1000 + 58 * self::DAY ) );
	}

	public function test_clear_carted_reverts_to_orphan_window(): void {
		$row = FileUploads::mark_carted( array( 'time' => 1000 ), 1000 );
		$row = FileUploads::clear_carted( $row );
		$this->assertTrue( FileUploads::is_expired( $row, 1000 + 3 * self::DAY ) );
	}
}
