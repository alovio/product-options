<?php
declare( strict_types=1 );

namespace CoreLabs\ProductOptions\Tests\Unit;

use CoreLabs\ProductOptions\Cart\OptionSanitizer;
use Brain\Monkey;

class OptionSanitizerTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\Functions\when( 'sanitize_text_field' )->returnArg( 1 );
		Monkey\Functions\when( 'sanitize_textarea_field' )->returnArg( 1 );
	}

	private function group( array $fields ): array {
		return array( 'version' => 1, 'fields' => $fields );
	}

	public function test_text_kept_and_empty_dropped(): void {
		$g = $this->group(
			array(
				array( 'id' => 'a', 'type' => 'text' ),
				array( 'id' => 'b', 'type' => 'text' ),
			)
		);
		$out = OptionSanitizer::sanitize( $g, array( 'a' => 'hi', 'b' => '' ) );
		$this->assertSame( array( 'a' => 'hi' ), $out );
	}

	public function test_number_cast_to_float(): void {
		$g   = $this->group( array( array( 'id' => 'n', 'type' => 'number' ) ) );
		$out = OptionSanitizer::sanitize( $g, array( 'n' => '12.5' ) );
		$this->assertSame( 12.5, $out['n'] );
	}

	public function test_checkbox_presence_normalized(): void {
		$g = $this->group( array( array( 'id' => 'c', 'type' => 'checkbox' ) ) );
		$this->assertSame( array( 'c' => 'yes' ), OptionSanitizer::sanitize( $g, array( 'c' => '1' ) ) );
		$this->assertSame( array(), OptionSanitizer::sanitize( $g, array() ) );
	}

	public function test_invalid_select_dropped_valid_kept(): void {
		$g = $this->group( array( array( 'id' => 's', 'type' => 'select', 'options' => array( 'S', 'M' ) ) ) );
		$this->assertSame( array(), OptionSanitizer::sanitize( $g, array( 's' => 'XL' ) ) );
		$this->assertSame( array( 's' => 'M' ), OptionSanitizer::sanitize( $g, array( 's' => 'M' ) ) );
	}

	public function test_date_value_kept(): void {
		$g = $this->group( array( array( 'id' => 'd', 'type' => 'date' ) ) );
		$this->assertSame( array( 'd' => '2026-06-20' ), OptionSanitizer::sanitize( $g, array( 'd' => '2026-06-20' ) ) );
		$this->assertSame( array(), OptionSanitizer::sanitize( $g, array( 'd' => '' ) ) );
	}

	public function test_swatch_value_validated_against_labels(): void {
		$g = $this->group( array( array( 'id' => 'sw', 'type' => 'swatch', 'options' => array( array( 'label' => 'Red', 'color' => '#f00' ), array( 'label' => 'Blue', 'color' => '#00f' ) ) ) ) );
		$this->assertSame( array( 'sw' => 'Blue' ), OptionSanitizer::sanitize( $g, array( 'sw' => 'Blue' ) ) );
		$this->assertSame( array(), OptionSanitizer::sanitize( $g, array( 'sw' => 'Purple' ) ) );
	}

	public function test_price_type_has_no_user_value(): void {
		$g = $this->group( array( array( 'id' => 'p', 'type' => 'price', 'price' => 5.0 ) ) );
		$this->assertSame( array(), OptionSanitizer::sanitize( $g, array( 'p' => 'whatever' ) ) );
	}

	public function test_email_kept_when_valid_dropped_when_garbage(): void {
		$g = $this->group( array( array( 'id' => 'e', 'type' => 'email' ) ) );
		$this->assertSame( array( 'e' => 'a@b.co' ), OptionSanitizer::sanitize( $g, array( 'e' => 'a@b.co' ) ) );
		$this->assertSame( array(), OptionSanitizer::sanitize( $g, array( 'e' => 'not-an-email' ) ) );
	}

	public function test_url_rejects_javascript_scheme(): void {
		$g = $this->group( array( array( 'id' => 'u', 'type' => 'url' ) ) );
		$this->assertSame( array( 'u' => 'https://x.test/a' ), OptionSanitizer::sanitize( $g, array( 'u' => 'https://x.test/a' ) ) );
		$this->assertSame( array(), OptionSanitizer::sanitize( $g, array( 'u' => 'javascript:alert(1)' ) ) ); // phpcs:ignore
	}

	public function test_phone_strips_letters_keeps_dial_chars(): void {
		$g = $this->group( array( array( 'id' => 'p2', 'type' => 'phone' ) ) );
		$this->assertSame( array( 'p2' => '+1 (555) 000-11' ), OptionSanitizer::sanitize( $g, array( 'p2' => '+1x (555)abc 000-11' ) ) );
		$this->assertSame( array(), OptionSanitizer::sanitize( $g, array( 'p2' => 'abcdef' ) ) );
	}

	public function test_time_trimmed_and_kept(): void {
		$g = $this->group( array( array( 'id' => 't2', 'type' => 'time' ) ) );
		$this->assertSame( array( 't2' => '14:30' ), OptionSanitizer::sanitize( $g, array( 't2' => ' 14:30 ' ) ) );
	}

	public function test_quantity_cast_to_int_and_clamped(): void {
		$g = $this->group( array( array( 'id' => 'q', 'type' => 'quantity', 'min' => '1', 'max' => '10', 'step' => '1' ) ) );
		$this->assertSame( array( 'q' => 3 ), OptionSanitizer::sanitize( $g, array( 'q' => '3.7' ) ) );
		$this->assertSame( array( 'q' => 10 ), OptionSanitizer::sanitize( $g, array( 'q' => '99' ) ) );
		$this->assertSame( array( 'q' => 1 ), OptionSanitizer::sanitize( $g, array( 'q' => '-5' ) ) );
		$this->assertSame( array(), OptionSanitizer::sanitize( $g, array( 'q' => '' ) ) );
	}

	public function test_buttons_allowlist_like_radio(): void {
		$g = $this->group( array( array( 'id' => 'b', 'type' => 'buttons', 'options' => array( 'Classic', 'Modern' ) ) ) );
		$this->assertSame( array( 'b' => 'Modern' ), OptionSanitizer::sanitize( $g, array( 'b' => 'Modern' ) ) );
		$this->assertSame( array(), OptionSanitizer::sanitize( $g, array( 'b' => 'Hacky' ) ) );
	}

	public function test_image_swatch_matches_on_label(): void {
		$g = $this->group( array( array( 'id' => 'i', 'type' => 'image_swatch', 'options' => array( array( 'label' => 'Oak', 'image' => 'https://x.test/oak.jpg' ) ) ) ) );
		$this->assertSame( array( 'i' => 'Oak' ), OptionSanitizer::sanitize( $g, array( 'i' => 'Oak' ) ) );
		$this->assertSame( array(), OptionSanitizer::sanitize( $g, array( 'i' => 'Pine' ) ) );
	}
}
