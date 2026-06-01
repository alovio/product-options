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

		$base   = ( is_object( $product ) && method_exists( $product, 'get_price' ) ) ? (float) $product->get_price() : 0.0;
		$active = ConditionalLogic::active_map( $group, array() );

		$has_priced = false;
		foreach ( $fields as $f ) {
			if ( ( isset( $f['price'] ) && (float) $f['price'] > 0 ) || 'price' === ( $f['type'] ?? '' ) ) {
				$has_priced = true;
				break;
			}
		}

		printf( '<div class="apo-options" data-apo-base="%s">', esc_attr( (string) $base ) );
		foreach ( $fields as $f ) {
			$this->render_field( $f, ! empty( $active[ $f['id'] ?? '' ] ) );
		}
		if ( $has_priced ) {
			echo '<p class="apo-options-total">' . esc_html__( 'Options total:', 'conditional-product-options' )
				. ' <span class="apo-options-total__value" aria-live="polite" aria-atomic="true">+0.00</span></p>';
		}
		echo '<script type="application/json" class="apo-rules">'
			. wp_json_encode( $group, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP )
			. '</script>';
		echo '</div>';
	}

	private function label_text( array $f ): string {
		$label = (string) ( $f['label'] ?? '' );
		return '' !== $label ? $label : self::type_label( (string) ( $f['type'] ?? '' ) );
	}

	private static function type_label( string $type ): string {
		switch ( $type ) {
			case 'textarea':
				return __( 'Text area', 'conditional-product-options' );
			case 'number':
				return __( 'Number', 'conditional-product-options' );
			case 'checkbox':
				return __( 'Checkbox', 'conditional-product-options' );
			case 'radio':
			case 'select':
			case 'swatch':
				return __( 'Choose an option', 'conditional-product-options' );
			case 'price':
				return __( 'Surcharge', 'conditional-product-options' );
			case 'date':
				return __( 'Date', 'conditional-product-options' );
			case 'text':
				return __( 'Text', 'conditional-product-options' );
			default:
				return __( 'Option', 'conditional-product-options' );
		}
	}

	private function required_marker( array $f ): string {
		if ( empty( $f['required'] ) ) {
			return '';
		}
		return ' <span class="apo-required" aria-hidden="true">*</span><span class="apo-sr-only">'
			. esc_html__( '(required)', 'conditional-product-options' ) . '</span>';
	}

	private function desc_markup( array $f, string $id ): string {
		$desc = (string) ( $f['description'] ?? '' );
		if ( '' === $desc ) {
			return '';
		}
		return sprintf( '<small class="apo-field__desc" id="%s">%s</small>', esc_attr( 'apo_desc_' . $id ), esc_html( $desc ) );
	}

	private function render_field( array $f, bool $active ): void {
		$id     = (string) $f['id'];
		$type   = (string) ( $f['type'] ?? '' );
		$hidden = $active ? '' : ' hidden';

		if ( 'heading' === $type ) {
			printf( '<div class="apo-field apo-field--heading" data-apo-field="%s"%s>', esc_attr( $id ), $hidden ); // phpcs:ignore WordPress.Security.EscapeOutput
			printf( '<h4 class="apo-heading">%s</h4>', esc_html( (string) ( $f['label'] ?? '' ) ) );
			echo $this->desc_markup( $f, $id ); // phpcs:ignore WordPress.Security.EscapeOutput
			echo '</div>';
			return;
		}

		$fid   = 'apo_' . $id;
		$group = in_array( $type, array( 'radio', 'swatch' ), true );

		printf( '<div class="apo-field" data-apo-field="%s"%s>', esc_attr( $id ), $hidden ); // phpcs:ignore WordPress.Security.EscapeOutput

		if ( $group ) {
			echo '<fieldset class="apo-fieldset"><legend class="apo-field__label">'
				. esc_html( $this->label_text( $f ) ) . $this->required_marker( $f ) . '</legend>'; // phpcs:ignore WordPress.Security.EscapeOutput
			echo $this->desc_markup( $f, $id ); // phpcs:ignore WordPress.Security.EscapeOutput
			$this->render_input( $f );
			echo '</fieldset>';
		} elseif ( 'price' === $type ) {
			echo '<span class="apo-field__label">' . esc_html( $this->label_text( $f ) ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput
			echo $this->desc_markup( $f, $id ); // phpcs:ignore WordPress.Security.EscapeOutput
			$this->render_input( $f );
		} else {
			printf(
				'<label class="apo-field__label" for="%s">%s%s</label>',
				esc_attr( $fid ),
				esc_html( $this->label_text( $f ) ),
				$this->required_marker( $f ) // phpcs:ignore WordPress.Security.EscapeOutput
			);
			echo $this->desc_markup( $f, $id ); // phpcs:ignore WordPress.Security.EscapeOutput
			$this->render_input( $f );
		}
		echo '</div>';
	}

	private function render_input( array $f ): void {
		$id       = (string) $f['id'];
		$name     = 'apo[' . $id . ']';
		$fid      = 'apo_' . $id;
		$type     = (string) $f['type'];
		$required = ! empty( $f['required'] );
		$req      = $required ? ' required aria-required="true"' : '';
		$rreq     = $required ? ' required' : '';
		$default  = (string) ( $f['default'] ?? '' );
		$ph       = ( '' !== (string) ( $f['placeholder'] ?? '' ) ) ? sprintf( ' placeholder="%s"', esc_attr( (string) $f['placeholder'] ) ) : '';
		$descby   = ( '' !== (string) ( $f['description'] ?? '' ) ) ? sprintf( ' aria-describedby="%s"', esc_attr( 'apo_desc_' . $id ) ) : '';

		switch ( $type ) {
			case 'textarea':
				$ml = ! empty( $f['maxLength'] ) ? sprintf( ' maxlength="%d"', (int) $f['maxLength'] ) : '';
				printf( '<textarea id="%s" name="%s" rows="3"%s%s%s%s>%s</textarea>', esc_attr( $fid ), esc_attr( $name ), $req, $ph, $ml, $descby, esc_textarea( $default ) ); // phpcs:ignore WordPress.Security.EscapeOutput
				break;
			case 'number':
				$min  = ( '' !== (string) ( $f['min'] ?? '' ) ) ? sprintf( ' min="%s"', esc_attr( (string) $f['min'] ) ) : '';
				$max  = ( '' !== (string) ( $f['max'] ?? '' ) ) ? sprintf( ' max="%s"', esc_attr( (string) $f['max'] ) ) : '';
				$step = ( '' !== (string) ( $f['step'] ?? '' ) ) ? sprintf( ' step="%s"', esc_attr( (string) $f['step'] ) ) : '';
				$val  = ( '' !== $default ) ? sprintf( ' value="%s"', esc_attr( $default ) ) : '';
				printf( '<input type="number" id="%s" name="%s"%s%s%s%s%s%s%s />', esc_attr( $fid ), esc_attr( $name ), $req, $ph, $min, $max, $step, $descby, $val ); // phpcs:ignore WordPress.Security.EscapeOutput
				break;
			case 'date':
				$min = ! empty( $f['min'] ) ? sprintf( ' min="%s"', esc_attr( (string) $f['min'] ) ) : '';
				$max = ! empty( $f['max'] ) ? sprintf( ' max="%s"', esc_attr( (string) $f['max'] ) ) : '';
				$val = ( '' !== $default ) ? sprintf( ' value="%s"', esc_attr( $default ) ) : '';
				printf( '<input type="date" id="%s" name="%s"%s%s%s%s%s />', esc_attr( $fid ), esc_attr( $name ), $req, $min, $max, $descby, $val ); // phpcs:ignore WordPress.Security.EscapeOutput
				break;
			case 'checkbox':
				$chk = ( 'yes' === $default ) ? ' checked' : '';
				printf( '<input type="checkbox" id="%s" name="%s" value="yes"%s%s%s />', esc_attr( $fid ), esc_attr( $name ), $req, $descby, $chk ); // phpcs:ignore WordPress.Security.EscapeOutput
				break;
			case 'radio':
				foreach ( (array) $f['options'] as $opt ) {
					$chk = ( (string) $opt === $default ) ? ' checked' : '';
					printf( '<label class="apo-opt"><input type="radio" name="%s" value="%s"%s%s /> %s</label>', esc_attr( $name ), esc_attr( $opt ), $rreq, $chk, esc_html( $opt ) ); // phpcs:ignore WordPress.Security.EscapeOutput
				}
				break;
			case 'select':
				printf( '<select id="%s" name="%s"%s%s><option value="">%s</option>', esc_attr( $fid ), esc_attr( $name ), $req, $descby, esc_html__( 'Choose an option', 'conditional-product-options' ) ); // phpcs:ignore WordPress.Security.EscapeOutput
				foreach ( (array) $f['options'] as $opt ) {
					$sel = ( (string) $opt === $default ) ? ' selected' : '';
					printf( '<option value="%s"%s>%s</option>', esc_attr( $opt ), $sel, esc_html( $opt ) ); // phpcs:ignore WordPress.Security.EscapeOutput
				}
				echo '</select>';
				break;
			case 'swatch':
				echo '<div class="apo-swatches">';
				foreach ( (array) $f['options'] as $opt ) {
					$opt_label = is_array( $opt ) ? (string) ( $opt['label'] ?? '' ) : (string) $opt;
					$opt_color = is_array( $opt ) ? (string) ( $opt['color'] ?? '#cccccc' ) : '#cccccc';
					$chk       = ( $opt_label === $default ) ? ' checked' : '';
					// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- label/name/color escaped via esc_attr; $rreq/$chk are internal constant attribute fragments.
					printf(
						'<label class="apo-swatch" title="%1$s"><input type="radio" name="%2$s" value="%1$s"%3$s%5$s aria-label="%1$s" /><span class="apo-swatch__dot" style="background-color:%4$s"></span><span class="apo-swatch__label">%1$s</span></label>',
						esc_attr( $opt_label ),
						esc_attr( $name ),
						$rreq,
						esc_attr( $opt_color ),
						$chk
					);
					// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
				}
				echo '</div>';
				break;
			case 'price':
				printf(
					'<input type="hidden" name="%s" value="yes" /><span class="apo-fee">+%s</span>',
					esc_attr( $name ),
					esc_html( (string) ( $f['price'] ?? 0 ) )
				);
				break;
			default: // text
				$ml  = ! empty( $f['maxLength'] ) ? sprintf( ' maxlength="%d"', (int) $f['maxLength'] ) : '';
				$val = ( '' !== $default ) ? sprintf( ' value="%s"', esc_attr( $default ) ) : '';
				printf( '<input type="text" id="%s" name="%s"%s%s%s%s%s />', esc_attr( $fid ), esc_attr( $name ), $req, $ph, $ml, $descby, $val ); // phpcs:ignore WordPress.Security.EscapeOutput
		}
	}
}
