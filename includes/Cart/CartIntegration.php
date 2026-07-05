<?php
declare( strict_types=1 );

namespace CoreLabs\ProductOptions\Cart;

use CoreLabs\ProductOptions\Groups\GroupResolver;

defined( 'ABSPATH' ) || exit;

/**
 * Wire option values through the cart and order. HPOS-safe: order data is
 * written via CRUD (add_meta_data), never direct SQL.
 */
final class CartIntegration {

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
		$groups = \CoreLabs\ProductOptions\Groups\GroupResolver::for_product( (int) $product_id );
		if ( empty( $groups ) ) {
			return $passed;
		}
		$errors = CartItemShape::collect_errors( $groups, $this->posted() );
		if ( ! empty( $errors ) ) {
			foreach ( $errors as $error ) {
				wc_add_notice( $error, 'error' );
			}
			return false;
		}
		return $passed;
	}

	/**
	 * Build one per-group entry for every resolved, non-empty group
	 * (spec §4: {group_id, options, base_price, addon_total, unique_key}).
	 *
	 * @param array<string,mixed> $cart_item_data
	 * @param int                 $product_id
	 * @param int                 $variation_id
	 * @return array<string,mixed>
	 */
	public function add_cart_item_data( $cart_item_data, $product_id, $variation_id = 0 ) {
		// Groups are resolved on the parent product; price comes from the
		// purchased entity (the variation when one is chosen).
		$groups = GroupResolver::for_product( (int) $product_id );
		if ( empty( $groups ) ) {
			return $cart_item_data;
		}
		$priced  = (int) $variation_id > 0 ? (int) $variation_id : (int) $product_id;
		$product = wc_get_product( $priced );
		$base    = $product ? (float) $product->get_price() : 0.0;
		$posted  = $this->posted();

		$entries = array();
		foreach ( $groups as $group ) {
			if ( empty( $group['fields'] ) ) {
				continue;
			}
			$opts      = OptionSanitizer::sanitize( $group, $posted );
			$entries[] = array(
				'group_id'    => (int) $group['id'],
				'options'     => $opts,
				'base_price'  => $base,
				'addon_total' => PriceCalculator::addon_total( $group, $opts, wc_get_price_decimals(), $base ),
				'unique_key'  => md5( $group['id'] . '|' . (string) wp_json_encode( $opts ) ),
			);
		}
		if ( array() !== $entries ) {
			$cart_item_data['apo'] = $entries;
		}
		return $cart_item_data;
	}

	/**
	 * Re-attach option data when the cart is rebuilt from session each request.
	 * Legacy 1.x payloads are normalized to the per-group list here (spec §3.5.4).
	 *
	 * @param array<string,mixed> $cart_item
	 * @param array<string,mixed> $values
	 * @return array<string,mixed>
	 */
	public function get_from_session( $cart_item, $values ) {
		if ( isset( $values['apo'] ) ) {
			$cart_item['apo'] = CartItemShape::normalize_apo( $values['apo'] );
		}
		return $cart_item;
	}

	/**
	 * Always recompute from the stored base price (idempotent across the
	 * multiple times this hook fires; no run-once guard). Entries whose group
	 * still resolves are recomputed; orphaned entries reuse their stored
	 * addon_total (no fatal, no silent free upgrade — spec §3.5.4).
	 *
	 * @param \WC_Cart $cart
	 */
	public function recalculate( $cart ): void {
		if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
			return;
		}
		foreach ( $cart->get_cart() as $key => $item ) {
			if ( empty( $item['apo'] ) || ! isset( $item['data'] ) ) {
				continue;
			}
			$entries = CartItemShape::normalize_apo( $item['apo'] );
			if ( array() === $entries ) {
				continue;
			}
			$product_id = isset( $item['product_id'] ) ? (int) $item['product_id'] : (int) $item['data']->get_id();
			$groups     = GroupResolver::for_product( $product_id );
			$base       = (float) $entries[0]['base_price'];
			$addon      = 0.0;
			foreach ( $entries as $i => $entry ) {
				$group = CartItemShape::pick_group( $groups, $entry['group_id'] );
				if ( null !== $group ) {
					$entry['addon_total'] = PriceCalculator::addon_total( $group, $entry['options'], wc_get_price_decimals(), $base );
					$entries[ $i ]        = $entry;
				}
				$addon += (float) $entry['addon_total'];
			}
			$cart->cart_contents[ $key ]['apo'] = $entries;
			$item['data']->set_price( $base + $addon );
		}
	}

	/**
	 * @param array<int,array{key:string,value:string}> $item_data
	 * @param array<string,mixed>                        $cart_item
	 * @return array<int,array{key:string,value:string}>
	 */
	public function display_in_cart( $item_data, $cart_item ) {
		$entries = CartItemShape::normalize_apo( $cart_item['apo'] ?? null );
		if ( array() === $entries ) {
			return $item_data;
		}
		$groups = GroupResolver::for_product( (int) ( $cart_item['product_id'] ?? 0 ) );
		foreach ( $entries as $entry ) {
			$fields = self::fields_by_id( CartItemShape::pick_group( $groups, $entry['group_id'] ) );
			foreach ( $entry['options'] as $fid => $val ) {
				$f           = $fields[ $fid ] ?? null;
				$item_data[] = array(
					'key'   => ( $f && '' !== $f['label'] ) ? $f['label'] : $fid,
					'value' => wc_clean( self::format_value( $f, $val ) ),
				);
			}
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
		$entries = CartItemShape::normalize_apo( $values['apo'] ?? null );
		if ( array() === $entries ) {
			return;
		}
		$groups = GroupResolver::for_product( (int) ( $values['product_id'] ?? 0 ) );
		foreach ( $entries as $entry ) {
			$fields = self::fields_by_id( CartItemShape::pick_group( $groups, $entry['group_id'] ) );
			foreach ( $entry['options'] as $fid => $val ) {
				$f = $fields[ $fid ] ?? null;
				$item->add_meta_data(
					( $f && '' !== $f['label'] ) ? $f['label'] : $fid,
					self::format_value( $f, $val ),
					true
				);
			}
		}
	}

	/**
	 * @param array<string,mixed>|null $group canonical group array (null when unresolved).
	 * @return array<string,array<string,mixed>> id => field definition
	 */
	private static function fields_by_id( ?array $group ): array {
		$map = array();
		foreach ( ( $group['fields'] ?? array() ) as $f ) {
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
