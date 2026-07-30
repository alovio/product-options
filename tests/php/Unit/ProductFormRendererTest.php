<?php
declare( strict_types=1 );

namespace CoreLabs\ProductOptions\Tests\Unit;

use CoreLabs\ProductOptions\Cart\PriceCalculator;
use CoreLabs\ProductOptions\Frontend\ProductFormRenderer;
use Brain\Monkey;
use ReflectionMethod;

/**
 * The display layer must never advertise a price the cart will not charge.
 * These call the private formatters directly (they render markup, so the
 * public path needs WordPress) and assert them AGAINST PriceCalculator — the
 * pairing is the point: the pill/label and the invoice come from one rule.
 */
final class ProductFormRendererTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		// Plain-text money so assertions read like the storefront does.
		Monkey\Functions\when( 'wc_price' )->alias( static fn( $a ) => '<span>&#36;' . number_format( (float) $a, 2 ) . '</span>' );
		Monkey\Functions\when( 'wp_strip_all_tags' )->alias( static fn( $s ) => strip_tags( (string) $s ) );
		Monkey\Functions\when( 'esc_html' )->returnArg( 1 );
		Monkey\Functions\when( 'esc_html__' )->returnArg( 1 );
	}

	/** Make one filter tag answer with $return; everything else passes through. */
	private function filter_returns( string $tag, $return ): void {
		Monkey\Functions\when( 'apply_filters' )->alias(
			static function ( $applied_tag, $value = null ) use ( $tag, $return ) {
				return $applied_tag === $tag ? $return : $value;
			}
		);
	}

	/** @param mixed ...$args */
	private function call( string $method, ...$args ) {
		$m = new ReflectionMethod( ProductFormRenderer::class, $method );
		$m->setAccessible( true );
		return $m->invoke( null, ...$args );
	}

	private function pill( array $f ): string {
		return wp_strip_all_tags( (string) $this->call( 'price_pill', $f ) );
	}

	private function optionText( array $f, $option ): string {
		return (string) $this->call( 'option_text', $f, $option );
	}

	private function charge( array $f, $value, float $base = 0.0 ): float {
		return PriceCalculator::addon_total(
			array( 'version' => 1, 'fields' => array( $f + array( 'condition' => null ) ) ),
			array( $f['id'] => $value ),
			2,
			$base
		);
	}

	private function mixed_field(): array {
		return array(
			'id'        => 'size',
			'type'      => 'radio',
			'label'     => 'Size',
			'price'     => 25,
			'priceMode' => 'fixed',
			'options'   => array( 'Standard', array( 'label' => 'Oversized', 'price' => 90 ) ),
		);
	}

	public function test_every_option_advertises_exactly_what_it_charges(): void {
		$f = $this->mixed_field();
		foreach ( array( 'Standard', 'Oversized' ) as $value ) {
			$this->assertStringContainsString(
				'$' . number_format( $this->charge( $f, $value ), 2 ),
				$this->optionText( $f, $value === 'Standard' ? 'Standard' : array( 'label' => 'Oversized', 'price' => 90 ) ),
				"option “{$value}” must show the amount it is charged"
			);
		}
	}

	public function test_pill_spans_the_field_price_fallback(): void {
		// The cheapest pick costs the field price, the dearest its own.
		$this->assertSame( '+$25.00 – +$90.00', $this->pill( $this->mixed_field() ) );
	}

	public function test_pill_collapses_when_every_option_costs_the_same(): void {
		$f = array(
			'id'      => 'size',
			'type'    => 'select',
			'price'   => 0,
			'options' => array( array( 'label' => 'A', 'price' => 40 ), array( 'label' => 'B', 'price' => 40 ) ),
		);
		$this->assertSame( '+$40.00', $this->pill( $f ) );
	}

	public function test_percent_mode_summarises_as_percentages(): void {
		$f = array(
			'id'        => 'speed',
			'type'      => 'buttons',
			'price'     => 0,
			'priceMode' => 'percent',
			'options'   => array( array( 'label' => 'Express', 'price' => 10 ), array( 'label' => 'Overnight', 'price' => 20 ) ),
		);
		$this->assertSame( '+10% – +20%', $this->pill( $f ) );
		$this->assertSame( 'Express (+10%)', $this->optionText( $f, $f['options'][0] ) );
		// …and the percentage really is of the base price.
		$this->assertSame( 20.0, $this->charge( $f, 'Express', 200.0 ) );
	}

	public function test_formula_fields_never_advertise_option_prices(): void {
		// A formula ignores prices entirely, so showing them would be a lie.
		$f = array(
			'id'        => 'size',
			'type'      => 'select',
			'price'     => 0,
			'priceMode' => 'formula',
			'formula'   => '2 * 3',
			'options'   => array( array( 'label' => '50x70', 'price' => 799 ) ),
		);
		$this->assertSame( 'price varies', $this->pill( $f ) );
		$this->assertSame( '50x70', $this->optionText( $f, $f['options'][0] ) );
		$this->assertSame( 6.0, $this->charge( $f, '50x70' ) );
	}

	public function test_duplicate_labels_advertise_the_price_that_is_charged(): void {
		$f = array(
			'id'      => 'size',
			'type'    => 'select',
			'price'   => 0,
			'options' => array( array( 'label' => 'Large', 'price' => 399 ), array( 'label' => 'Large', 'price' => 799 ) ),
		);
		$charged = $this->charge( $f, 'Large' );
		$this->assertSame( 399.0, $charged );
		// The second option must NOT advertise 799 when 399 is what is billed.
		$this->assertSame( 'Large (+$399.00)', $this->optionText( $f, $f['options'][1] ) );
	}

	public function test_fields_without_per_option_prices_keep_bare_labels(): void {
		// The field pill already states the price once — no noise on options.
		$f = array( 'id' => 'size', 'type' => 'select', 'price' => 8, 'options' => array( 'S', 'M' ) );
		$this->assertSame( 'S', $this->optionText( $f, 'S' ) );
		$this->assertSame( '+$8.00', $this->pill( $f ) );
	}

	private function priced_field(): array {
		return array( 'id' => 'size', 'type' => 'select', 'price' => 0, 'options' => array( array( 'label' => 'A', 'price' => 40 ) ) );
	}

	public function test_suffix_filter_can_rewrite_the_price(): void {
		$f = $this->priced_field();
		$this->filter_returns( 'clpo_option_price_suffix', 'from 40' );
		$this->assertSame( 'A (from 40)', $this->optionText( $f, $f['options'][0] ) );
	}

	public function test_suffix_filter_can_remove_the_price(): void {
		$f = $this->priced_field();
		$this->filter_returns( 'clpo_option_price_suffix', '' );
		$this->assertSame( 'A', $this->optionText( $f, $f['options'][0] ) );
	}

	public function test_money_decodes_the_currency_entity(): void {
		$this->assertSame( '$5.50', $this->call( 'money', 5.5 ) );
	}
}
