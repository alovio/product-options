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

	public function test_webp_allowed_by_default(): void {
		$this->assertContains( 'webp', FileUploads::allowed_extensions() );
	}

	public function test_parse_tokens_single_and_comma_list(): void {
		$t1 = str_repeat( 'a1', 16 );
		$t2 = str_repeat( 'b2', 16 );
		$this->assertSame( array( $t1 ), FileUploads::parse_tokens( $t1 ) );
		$this->assertSame( array( $t1, $t2 ), FileUploads::parse_tokens( $t1 . ',' . $t2 ) );
		$this->assertSame( array( $t1, $t2 ), FileUploads::parse_tokens( ' ' . $t1 . ' , ' . $t2 . ' ' ) );
	}

	public function test_parse_tokens_drops_garbage_dupes_and_non_strings(): void {
		$t1 = str_repeat( 'a1', 16 );
		$this->assertSame( array( $t1 ), FileUploads::parse_tokens( $t1 . ',' . $t1 . ',nope,,' . strtoupper( $t1 ) ) );
		$this->assertSame( array(), FileUploads::parse_tokens( null ) );
		$this->assertSame( array(), FileUploads::parse_tokens( array( $t1 ) ) );
		$this->assertSame( array(), FileUploads::parse_tokens( '' ) );
	}
}
