<?php
declare( strict_types=1 );

namespace CoreLabs\ProductOptions\Cart;

use CoreLabs\ProductOptions\Fields\FieldRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Wire option values through the cart and order. HPOS-safe: order data is
 * written via CRUD (add_meta_data), never direct SQL.
 */
final class CartIntegration {

	private FieldRepository $repo;

	public function __construct( ?FieldRepository $repo = null ) {
		$this->repo = $repo ?? new FieldRepository();
	}

	public function register(): void {
		add_filter( 'woocommerce_add_to_cart_validation', array( $this, 'validate' ), 10, 3 );
		add_filter( 'woocommerce_add_cart_item_data', array( $this, 'add_cart_item_data' ), 10, 3 );
		add_filter( 'woocommerce_get_cart_item_from_session', array( $this, 'get_from_session' ), 10, 2 );
		add_action( 'woocommerce_before_calculate_totals', array( $this, 'recalculate' ), 20, 1 );
		add_filter( 'woocommerce_get_item_data', array( $this, 'display_in_cart' ), 10, 2 );
		add_action( 'woocommerce_checkout_create_order_line_item', array( $this, 'add_order_item_meta' ), 10, 4 );
	}

	/**
	 * Read the posted options (unslashed). WooCommerce handles the add-to-cart nonce.
	 *
	 * @return array<string,mixed>
	 */
	private function posted(): array {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( ! isset( $_POST['apo'] ) || ! is_array( $_POST['apo'] ) ) {
			return array();
		}
		// Blanket text-sanitize here (the add-to-cart nonce is handled by WooCommerce);
		// OptionSanitizer then applies per-field, type-aware sanitization.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		return map_deep( wp_unslash( $_POST['apo'] ), 'sanitize_text_field' );
	}

	/**
	 * @param bool $passed
	 * @param int  $product_id
	 * @param int  $quantity
	 * @return bool
	 */
	public function validate( $passed, $product_id, $quantity ) {
		$group = $this->repo->get( (int) $product_id );
		if ( empty( $group['fields'] ) ) {
			return $passed;
		}
		$opts   = OptionSanitizer::sanitize( $group, $this->posted() );
		$errors = Validator::validate( $group, $opts );
		if ( ! empty( $errors ) ) {
			foreach ( $errors as $error ) {
				wc_add_notice( $error, 'error' );
			}
			return false;
		}
		return $passed;
	}

	/**
	 * @param array<string,mixed> $cart_item_data
	 * @param int                 $product_id
	 * @param int                 $variation_id
	 * @return array<string,mixed>
	 */
	public function add_cart_item_data( $cart_item_data, $product_id, $variation_id = 0 ) {
		// Field groups are configured on the parent product; price comes from the
		// purchased entity (the variation when one is chosen).
		$group = $this->repo->get( (int) $product_id );
		if ( empty( $group['fields'] ) ) {
			return $cart_item_data;
		}
		$opts    = OptionSanitizer::sanitize( $group, $this->posted() );
		$priced  = (int) $variation_id > 0 ? (int) $variation_id : (int) $product_id;
		$product = wc_get_product( $priced );
		$base    = $product ? (float) $product->get_price() : 0.0;

		$cart_item_data['apo'] = array(
			'options'    => $opts,
			'base_price' => $base,
			'unique_key' => md5( (string) wp_json_encode( $opts ) ),
		);
		return $cart_item_data;
	}

	/**
	 * Re-attach option data when the cart is rebuilt from session each request.
	 *
	 * @param array<string,mixed> $cart_item
	 * @param array<string,mixed> $values
	 * @return array<string,mixed>
	 */
	public function get_from_session( $cart_item, $values ) {
		if ( isset( $values['apo'] ) ) {
			$cart_item['apo'] = $values['apo'];
		}
		return $cart_item;
	}

	/**
	 * Always recompute from the stored base price (idempotent across the
	 * multiple times this hook fires; no run-once guard).
	 *
	 * @param \WC_Cart $cart
	 */
	public function recalculate( $cart ): void {
		if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
			return;
		}
		foreach ( $cart->get_cart() as $item ) {
			// Require a stored base price; otherwise recomputing from the
			// already-mutated product price would stack the add-on on repeat fires.
			if ( empty( $item['apo'] ) || ! isset( $item['data'], $item['apo']['base_price'] ) ) {
				continue;
			}
			$product_id = isset( $item['product_id'] ) ? (int) $item['product_id'] : (int) $item['data']->get_id();
			$group      = $this->repo->get( $product_id );
			$base       = (float) $item['apo']['base_price'];
			$addon      = PriceCalculator::addon_total( $group, $item['apo']['options'] ?? array(), wc_get_price_decimals(), $base );
			$item['data']->set_price( $base + $addon );
		}
	}

	/**
	 * @param array<int,array{key:string,value:string}> $item_data
	 * @param array<string,mixed>                        $cart_item
	 * @return array<int,array{key:string,value:string}>
	 */
	public function display_in_cart( $item_data, $cart_item ) {
		if ( empty( $cart_item['apo']['options'] ) ) {
			return $item_data;
		}
		$fields = $this->fields_by_id( (int) ( $cart_item['product_id'] ?? 0 ) );
		foreach ( $cart_item['apo']['options'] as $fid => $val ) {
			$f           = $fields[ $fid ] ?? null;
			$item_data[] = array(
				'key'   => ( $f && '' !== $f['label'] ) ? $f['label'] : $fid,
				'value' => wc_clean( self::format_value( $f, $val ) ),
			);
		}
		return $item_data;
	}

	/**
	 * @param \WC_Order_Item_Product $item
	 * @param string                 $cart_item_key
	 * @param array<string,mixed>    $values
	 * @param \WC_Order              $order
	 */
	public function add_order_item_meta( $item, $cart_item_key, $values, $order ): void {
		if ( empty( $values['apo']['options'] ) ) {
			return;
		}
		$fields = $this->fields_by_id( (int) ( $values['product_id'] ?? 0 ) );
		foreach ( $values['apo']['options'] as $fid => $val ) {
			$f = $fields[ $fid ] ?? null;
			$item->add_meta_data(
				( $f && '' !== $f['label'] ) ? $f['label'] : $fid,
				self::format_value( $f, $val ),
				true
			);
		}
	}

	/** @return array<string,array<string,mixed>> id => field definition */
	private function fields_by_id( int $product_id ): array {
		$map = array();
		foreach ( $this->repo->get( $product_id )['fields'] ?? array() as $f ) {
			$map[ $f['id'] ] = $f;
		}
		return $map;
	}

	/**
	 * Human-readable display value for cart/order/email.
	 *
	 * @param array<string,mixed>|null $field
	 * @param mixed                    $val
	 */
	private static function format_value( $field, $val ): string {
		if ( is_array( $val ) ) {
			$val = implode( ', ', $val );
		}
		$type = $field['type'] ?? '';
		if ( 'checkbox' === $type ) {
			return _x( 'Yes', 'checked option in cart/order', 'corelabs-product-options' );
		}
		if ( 'number' === $type && is_numeric( $val ) ) {
			$num = (float) $val;
			return ( floor( $num ) === $num ) ? (string) (int) $num : (string) $num;
		}
		return (string) $val;
	}
}
