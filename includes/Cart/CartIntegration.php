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
		add_action( 'woocommerce_cart_item_removed', array( $this, 'release_file_tokens' ), 10, 2 );
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
		$posted = $this->posted();
		$errors = CartItemShape::collect_errors( $groups, $posted );
		foreach ( $groups as $g ) {
			$errors = array_merge( $errors, FileUploads::validate_tokens( $g, OptionSanitizer::sanitize( $g, $posted ) ) );
		}
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
			self::each_file_token( $entries, array( FileUploads::class, 'mark_carted_token' ) );
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
			self::each_file_token( $cart_item['apo'], array( FileUploads::class, 'mark_carted_token' ) );
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
		$show_prices = (bool) apply_filters( 'clpo_cart_option_prices', true );
		$groups      = GroupResolver::for_product( (int) ( $cart_item['product_id'] ?? 0 ) );
		foreach ( $entries as $entry ) {
			$group   = CartItemShape::pick_group( $groups, $entry['group_id'] );
			$fields  = self::fields_by_id( $group );
			$amounts = array();
			if ( $show_prices && null !== $group ) {
				foreach ( PriceCalculator::breakdown( $group, (array) $entry['options'], wc_get_price_decimals(), (float) $entry['base_price'] ) as $row ) {
					$amounts[ $row['field_id'] ] = $row['amount'];
				}
			}
			foreach ( $entry['options'] as $fid => $val ) {
				$f           = $fields[ $fid ] ?? null;
				$item_data[] = array(
					'key'   => ( $f && '' !== $f['label'] ) ? $f['label'] : $fid,
					'value' => self::with_price( esc_html( self::format_value( $f, $val ) ), $amounts[ $fid ] ?? 0.0 ),
				);
			}
			// Surcharge fields have no submitted value but do charge — list them
			// too, so the cart explains the whole line price.
			foreach ( $amounts as $fid => $amount ) {
				$f = $fields[ $fid ] ?? null;
				if ( $f && 'price' === ( $f['type'] ?? '' ) ) {
					$item_data[] = array(
						'key'   => ( '' !== $f['label'] ) ? $f['label'] : $fid,
						'value' => self::with_price( '', $amount ),
					);
				}
			}
		}
		return $item_data;
	}

	/**
	 * Append a field's price contribution to its display value as a chip:
	 * 'Oak <span class="apo-cart-fee">+$5.00</span>'. $display must arrive
	 * escaped — the chip is the only markup added. Both carts keep the span
	 * (the Block cart's sanitizer allows class but strips inline styles).
	 */
	public static function with_price( string $display, float $amount ): string {
		if ( $amount <= 0 ) {
			return $display;
		}
		$price = function_exists( 'wc_price' )
			? html_entity_decode( wp_strip_all_tags( wc_price( $amount ) ), ENT_QUOTES, 'UTF-8' )
			: number_format( $amount, 2 );
		$chip  = '<span class="apo-cart-fee">+' . esc_html( $price ) . '</span>';
		return '' === $display ? $chip : $display . ' ' . $chip;
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
				if ( $f && 'file' === ( $f['type'] ?? '' ) ) {
					$parts = array();
					foreach ( FileUploads::parse_tokens( $val ) as $token ) {
						$resolved = FileUploads::consume( $token );
						$parts[]  = $resolved ? $resolved['name'] . ' — ' . $resolved['url'] : $token;
					}
					$item->add_meta_data(
						( '' !== $f['label'] ) ? $f['label'] : $fid,
						array() !== $parts ? implode( ', ', $parts ) : (string) $val,
						true
					);
					continue;
				}
				$item->add_meta_data(
					( $f && '' !== $f['label'] ) ? $f['label'] : $fid,
					self::format_value( $f, $val ),
					true
				);
			}
		}
	}

	/**
	 * @param array<string,mixed> $removed_key
	 * @param \WC_Cart            $cart
	 */
	public function release_file_tokens( $removed_key, $cart ): void {
		$item = $cart->removed_cart_contents[ $removed_key ] ?? null;
		if ( ! is_array( $item ) || empty( $item['apo'] ) ) {
			return;
		}
		self::each_file_token( CartItemShape::normalize_apo( $item['apo'] ), array( FileUploads::class, 'clear_carted_token' ) );
	}

	/**
	 * Walk every 32-hex file token in the per-group entries.
	 *
	 * @param array[]  $entries normalized apo entries.
	 * @param callable $cb      receives the token string.
	 */
	private static function each_file_token( array $entries, callable $cb ): void {
		foreach ( $entries as $entry ) {
			foreach ( (array) ( $entry['options'] ?? array() ) as $val ) {
				foreach ( FileUploads::parse_tokens( $val ) as $token ) {
					$cb( $token );
				}
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
		if ( 'file' === $type && is_string( $val ) ) {
			$names = array();
			foreach ( FileUploads::parse_tokens( $val ) as $token ) {
				$name    = FileUploads::display_name( $token );
				$names[] = '' !== $name ? $name : __( 'Uploaded file', 'corelabs-product-options' );
			}
			if ( array() !== $names ) {
				return implode( ', ', $names );
			}
		}
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
