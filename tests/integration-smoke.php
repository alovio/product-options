<?php
/**
 * Real-environment smoke test (run inside wp-env, WooCommerce active):
 *   wp-env run cli -- wp eval-file wp-content/plugins/woo-product-options/tests/integration-smoke.php
 *
 * Exercises the full money path against real WooCommerce: product meta
 * round-trip, conditional logic, price calculation, validation, sanitization.
 */

use APO\Fields\FieldRepository;
use APO\Cart\PriceCalculator;
use APO\Cart\OptionSanitizer;
use APO\Cart\Validator;

$GLOBALS['apo_fail'] = false;

function apo_check( bool $cond, string $msg ): void {
	echo ( $cond ? 'PASS' : 'FAIL' ) . " - {$msg}\n";
	if ( ! $cond ) {
		$GLOBALS['apo_fail'] = true;
	}
}

if ( ! class_exists( 'WooCommerce' ) ) {
	echo "FAIL - WooCommerce not active\n";
	exit( 1 );
}
if ( ! class_exists( FieldRepository::class ) ) {
	echo "FAIL - APO autoloader not loaded (plugin active?)\n";
	exit( 1 );
}

// 1. Create a product.
$product = new WC_Product_Simple();
$product->set_name( 'APO Test Product' );
$product->set_regular_price( '100' );
$pid = $product->save();
apo_check( $pid > 0, "created product #{$pid}" );

// 2. Save a field group (with one invalid type that must be dropped).
$repo  = new FieldRepository();
$group = array(
	'fields' => array(
		array( 'id' => 'engrave', 'type' => 'checkbox', 'label' => 'Engrave', 'price' => 5 ),
		array(
			'id'        => 'text',
			'type'      => 'text',
			'label'     => 'Engraving text',
			'required'  => true,
			'condition' => array( 'field' => 'engrave', 'operator' => 'is', 'value' => 'yes', 'action' => 'show' ),
		),
		array( 'id' => 'evil', 'type' => 'bogus', 'label' => 'drop me' ),
	),
);
$saved = $repo->save( $pid, $group );
apo_check( count( $saved['fields'] ) === 2, 'unknown type dropped on save (got ' . count( $saved['fields'] ) . ')' );

// 3. Round-trip read.
$read = $repo->get( $pid );
apo_check( count( $read['fields'] ) === 2, 'round-trip read 2 fields' );
apo_check( ( $read['fields'][1]['condition']['field'] ?? '' ) === 'engrave', 'condition preserved through storage' );

// 4. Price calculation (real WC decimals).
$decimals = wc_get_price_decimals();
$total    = PriceCalculator::addon_total( $read, array( 'engrave' => 'yes', 'text' => 'Hi' ), $decimals );
apo_check( abs( $total - 5.0 ) < 0.001, "addon total = 5.00 when engaged (got {$total})" );
$total0 = PriceCalculator::addon_total( $read, array(), $decimals );
apo_check( abs( $total0 - 0.0 ) < 0.001, "addon total = 0.00 when not engaged (got {$total0})" );

// 5. Conditional validation.
$err_on  = Validator::validate( $read, array( 'engrave' => 'yes' ) ); // text active + required + empty.
apo_check( count( $err_on ) === 1, 'required text errors when engrave is on (got ' . count( $err_on ) . ')' );
$err_off = Validator::validate( $read, array( 'engrave' => '' ) ); // text hidden.
apo_check( count( $err_off ) === 0, 'no error when text hidden (got ' . count( $err_off ) . ')' );

// 6. Sanitization.
$opts = OptionSanitizer::sanitize( $read, array( 'engrave' => '1', 'text' => 'Hello' ) );
apo_check( ( $opts['engrave'] ?? '' ) === 'yes', 'checkbox normalized to "yes"' );
apo_check( ( $opts['text'] ?? '' ) === 'Hello', 'text value sanitized + kept' );

// Cleanup.
wp_delete_post( $pid, true );

echo $GLOBALS['apo_fail'] ? "\nRESULT: FAIL\n" : "\nRESULT: ALL PASS\n";
