<?php
declare( strict_types=1 );

namespace APO\Frontend;

use APO\Fields\FieldRepository;
use APO\Logic\ConditionalLogic;

defined( 'ABSPATH' ) || exit;

/**
 * Render a product's option fields inside the add-to-cart form. All output is
 * escaped; initial visibility is computed server-side so there is no flash for
 * cached/no-JS visitors.
 */
final class ProductFormRenderer {

	private FieldRepository $repo;

	public function __construct( ?FieldRepository $repo = null ) {
		$this->repo = $repo ?? new FieldRepository();
	}

	public function register(): void {
		add_action( 'woocommerce_before_add_to_cart_button', array( $this, 'render' ) );
	}

	public function render(): void {
		global $product;
		$product_id = ( is_object( $product ) && method_exists( $product, 'get_id' ) ) ? (int) $product->get_id() : 0;
		if ( ! $product_id ) {
			return;
		}

		$group  = $this->repo->get( $product_id );
		$fields = $group['fields'] ?? array();
		if ( empty( $fields ) ) {
			return;
		}

		$active = ConditionalLogic::active_map( $group, array() );
		echo '<div class="apo-options">';
		foreach ( $fields as $f ) {
			$this->render_field( $f, ! empty( $active[ $f['id'] ?? '' ] ) );
		}
		echo '<p class="apo-options-total">' . esc_html__( 'Options total:', 'conditional-product-options' )
			. ' <span class="apo-options-total__value">+0.00</span></p>';
		echo '<script type="application/json" class="apo-rules">'
			. wp_json_encode( $group, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP )
			. '</script>';
		echo '</div>';
	}

	private function render_field( array $f, bool $active ): void {
		$id     = (string) $f['id'];
		$hidden = $active ? '' : ' hidden';
		printf( '<div class="apo-field" data-apo-field="%s"%s>', esc_attr( $id ), $hidden ); // phpcs:ignore WordPress.Security.EscapeOutput
		printf(
			'<label for="%s">%s%s</label>',
			esc_attr( 'apo_' . $id ),
			esc_html( '' !== $f['label'] ? $f['label'] : $f['type'] ),
			! empty( $f['required'] ) ? ' *' : ''
		);
		$this->render_input( $f );
		echo '</div>';
	}

	private function render_input( array $f ): void {
		$id   = (string) $f['id'];
		$name = 'apo[' . $id . ']';
		$fid  = 'apo_' . $id;
		$req  = ! empty( $f['required'] ) ? ' required' : '';
		$type = (string) $f['type'];

		switch ( $type ) {
			case 'textarea':
				printf( '<textarea id="%s" name="%s"%s></textarea>', esc_attr( $fid ), esc_attr( $name ), $req ); // phpcs:ignore WordPress.Security.EscapeOutput
				break;
			case 'number':
				printf( '<input type="number" id="%s" name="%s"%s />', esc_attr( $fid ), esc_attr( $name ), $req ); // phpcs:ignore WordPress.Security.EscapeOutput
				break;
			case 'checkbox':
				printf( '<input type="checkbox" id="%s" name="%s" value="yes"%s />', esc_attr( $fid ), esc_attr( $name ), $req ); // phpcs:ignore WordPress.Security.EscapeOutput
				break;
			case 'radio':
				foreach ( (array) $f['options'] as $opt ) {
					printf(
						'<label class="apo-opt"><input type="radio" name="%s" value="%s" /> %s</label>',
						esc_attr( $name ),
						esc_attr( $opt ),
						esc_html( $opt )
					);
				}
				break;
			case 'select':
				printf( '<select id="%s" name="%s"%s><option value="">&mdash;</option>', esc_attr( $fid ), esc_attr( $name ), $req ); // phpcs:ignore WordPress.Security.EscapeOutput
				foreach ( (array) $f['options'] as $opt ) {
					printf( '<option value="%s">%s</option>', esc_attr( $opt ), esc_html( $opt ) );
				}
				echo '</select>';
				break;
			case 'price':
				printf(
					'<input type="hidden" name="%s" value="yes" /><span class="apo-fee">+%s</span>',
					esc_attr( $name ),
					esc_html( (string) ( $f['price'] ?? 0 ) )
				);
				break;
			default: // text
				printf( '<input type="text" id="%s" name="%s"%s />', esc_attr( $fid ), esc_attr( $name ), $req ); // phpcs:ignore WordPress.Security.EscapeOutput
		}
	}
}
