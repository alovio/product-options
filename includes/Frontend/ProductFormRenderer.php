<?php
declare( strict_types=1 );

namespace CoreLabs\ProductOptions\Frontend;

use CoreLabs\ProductOptions\Groups\GroupResolver;
use CoreLabs\ProductOptions\Logic\ConditionalLogic;

defined( 'ABSPATH' ) || exit;

/**
 * Render every resolved option group inside the add-to-cart form, each as its
 * own .apo-options block (spec §3.2). All output is escaped; initial
 * visibility is computed server-side so there is no flash for cached/no-JS
 * visitors.
 *
 * Known limitation (spec-level): field ids are only unique within a group; if
 * two resolved groups share a field id, their `apo[<id>]` inputs collide.
 * Builder-generated ids are effectively unique, so this is accepted for 2.0.
 */
final class ProductFormRenderer {

	public function register(): void {
		add_action( 'woocommerce_before_add_to_cart_button', array( $this, 'render' ) );
	}

	public function render(): void {
		global $product;
		$product_id = ( is_object( $product ) && method_exists( $product, 'get_id' ) ) ? (int) $product->get_id() : 0;
		if ( ! $product_id ) {
			return;
		}

		$base = ( is_object( $product ) && method_exists( $product, 'get_price' ) ) ? (float) $product->get_price() : 0.0;

		$groups = GroupResolver::for_product( $product_id );
		foreach ( $groups as $group ) {
			$this->render_group( $group, $base );
		}
		$this->render_breakdown_box( $groups, $base );
	}

	/**
	 * One shared price-breakdown box (spec §9, design B): base price, each
	 * engaged priced option, dashed Total. Server renders the initial (default
	 * values) state; the frontend bundle re-renders live.
	 *
	 * @param array[] $groups resolved canonical groups.
	 */
	private function render_breakdown_box( array $groups, float $base ): void {
		$has_priced = false;
		$rows       = array();
		foreach ( $groups as $group ) {
			foreach ( (array) ( $group['fields'] ?? array() ) as $f ) {
				if ( ( isset( $f['price'] ) && (float) $f['price'] > 0 ) || 'price' === ( $f['type'] ?? '' ) || 'formula' === ( $f['priceMode'] ?? '' ) ) {
					$has_priced = true;
					break 2;
				}
			}
		}
		if ( ! $has_priced ) {
			return;
		}

		$decimals = function_exists( 'wc_get_price_decimals' ) ? wc_get_price_decimals() : 2;
		foreach ( $groups as $group ) {
			$rows = array_merge( $rows, \CoreLabs\ProductOptions\Cart\PriceCalculator::breakdown( $group, array(), $decimals, $base ) );
		}

		$fmt = static function ( float $amount ): string {
			return function_exists( 'wc_price' ) ? wp_strip_all_tags( wc_price( $amount ) ) : number_format( $amount, 2 );
		};

		printf( '<div class="apo-breakdown" data-apo-breakdown aria-live="polite" aria-atomic="true"%s><ul>', empty( $rows ) ? ' hidden' : '' ); // phpcs:ignore WordPress.Security.EscapeOutput
		if ( ! empty( $rows ) ) {
			$sum = 0.0;
			printf( '<li class="apo-breakdown__row"><span>%s</span><span>%s</span></li>', esc_html__( 'Base price', 'corelabs-product-options' ), esc_html( $fmt( $base ) ) );
			foreach ( $rows as $row ) {
				$sum += $row['amount'];
				printf( '<li class="apo-breakdown__row"><span>%s</span><span>+%s</span></li>', esc_html( $row['label'] ), esc_html( $fmt( $row['amount'] ) ) );
			}
			printf( '<li class="apo-breakdown__row apo-breakdown__row--total"><span>%s</span><span>%s</span></li>', esc_html__( 'Total', 'corelabs-product-options' ), esc_html( $fmt( $base + $sum ) ) );
		}
		echo '</ul></div>';
	}

	/** @param array<string,mixed> $group canonical group array (id/fields/…). */
	private function render_group( array $group, float $base ): void {
		$fields = $group['fields'] ?? array();
		if ( empty( $fields ) ) {
			return;
		}

		$active = ConditionalLogic::active_map( $group, array() );

		printf(
			'<div class="apo-options" data-apo-group="%d" data-apo-base="%s">',
			(int) ( $group['id'] ?? 0 ),
			esc_attr( (string) $base )
		);
		foreach ( $fields as $f ) {
			$this->render_field( $f, ! empty( $active[ $f['id'] ?? '' ] ) );
		}
		echo '<script type="application/json" class="apo-rules">'
			. wp_json_encode( array( 'fields' => $fields ), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP )
			. '</script>';
		echo '</div>';
	}

	private function label_text( array $f ): string {
		$label = (string) ( $f['label'] ?? '' );
		return '' !== $label ? $label : self::type_label( (string) ( $f['type'] ?? '' ) );
	}

	/**
	 * The price pill shown at the right edge of a field-card header:
	 * "+$8.00" / "+$2.00 each" / "+$0.50 / character" / "+10%" /
	 * "price varies" (formula). Empty when the field carries no charge.
	 */
	private static function price_pill( array $f ): string {
		$mode  = (string) ( $f['priceMode'] ?? 'fixed' );
		$price = isset( $f['price'] ) ? (float) $f['price'] : 0.0;

		$text = '';
		$fmt  = static function ( float $amount ): string {
			return function_exists( 'wc_price' ) ? wp_strip_all_tags( wc_price( $amount ) ) : number_format( $amount, 2 );
		};
		if ( 'formula' === $mode ) {
			$text = __( 'price varies', 'corelabs-product-options' );
		} elseif ( $price <= 0 || 'price' === ( $f['type'] ?? '' ) ) {
			$text = ''; // surcharge fields already print their own +fee row.
		} elseif ( 'percent' === $mode ) {
			/* translators: %s: percentage number */
			$text = sprintf( __( '+%s%%', 'corelabs-product-options' ), (string) ( $price + 0 ) );
		} elseif ( 'per_unit' === $mode ) {
			/* translators: %s: formatted price */
			$text = sprintf( __( '+%s each', 'corelabs-product-options' ), $fmt( $price ) );
		} elseif ( 'per_char' === $mode ) {
			/* translators: %s: formatted price */
			$text = sprintf( __( '+%s / character', 'corelabs-product-options' ), $fmt( $price ) );
		} else {
			$text = '+' . $fmt( $price );
		}

		return '' === $text ? '' : '<span class="apo-price-pill">' . esc_html( $text ) . '</span>';
	}

	private static function type_label( string $type ): string {
		switch ( $type ) {
			case 'textarea':
				return __( 'Text area', 'corelabs-product-options' );
			case 'number':
				return __( 'Number', 'corelabs-product-options' );
			case 'checkbox':
				return __( 'Checkbox', 'corelabs-product-options' );
			case 'radio':
			case 'select':
			case 'swatch':
				return __( 'Choose an option', 'corelabs-product-options' );
			case 'price':
				return __( 'Surcharge', 'corelabs-product-options' );
			case 'date':
				return __( 'Date', 'corelabs-product-options' );
			case 'text':
				return __( 'Text', 'corelabs-product-options' );
			default:
				return __( 'Option', 'corelabs-product-options' );
		}
	}

	private function required_marker( array $f ): string {
		if ( empty( $f['required'] ) ) {
			return '';
		}
		return ' <span class="apo-required" aria-hidden="true">*</span><span class="apo-sr-only">'
			. esc_html__( '(required)', 'corelabs-product-options' ) . '</span>';
	}

	private function desc_markup( array $f, string $id ): string {
		// Backward-compatible wrapper (used by the heading branch, which has no
		// inline label-text to host the toggle): toggle + panel together.
		return $this->tip_toggle( $f, $id ) . $this->tip_panel( $f, $id );
	}

	/**
	 * The keyboard-accessible ? toggle (spec §9). Rendered INSIDE the field's
	 * label-text span so it sits inline with the label — its CSS
	 * (.apo-tip-toggle: inline-flex, margin-left) expects that. Empty when the
	 * field has no description.
	 */
	private function tip_toggle( array $f, string $id ): string {
		if ( '' === (string) ( $f['description'] ?? '' ) ) {
			return '';
		}
		return sprintf(
			'<button type="button" class="apo-tip-toggle" aria-expanded="false" aria-controls="%1$s" aria-label="%2$s">?</button>',
			esc_attr( 'clpo_desc_' . $id ),
			esc_attr__( 'More information', 'corelabs-product-options' )
		);
	}

	/**
	 * The collapsible description panel — a block after the label, keeping the
	 * clpo_desc_ id so the toggle's aria-controls / the input's
	 * aria-describedby wiring still resolves.
	 */
	private function tip_panel( array $f, string $id ): string {
		$desc = (string) ( $f['description'] ?? '' );
		if ( '' === $desc ) {
			return '';
		}
		return sprintf(
			'<small class="apo-tip apo-field__desc" id="%1$s" hidden>%2$s</small>',
			esc_attr( 'clpo_desc_' . $id ),
			esc_html( $desc )
		);
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

		$fid   = 'clpo_' . $id;
		$group = in_array( $type, array( 'radio', 'swatch', 'buttons', 'image_swatch' ), true );

		printf( '<div class="apo-field" data-apo-field="%s"%s>', esc_attr( $id ), $hidden ); // phpcs:ignore WordPress.Security.EscapeOutput

		if ( $group ) {
			echo '<fieldset class="apo-fieldset"><legend class="apo-field__label"><span class="apo-field__label-text">'
				. esc_html( $this->label_text( $f ) ) . $this->required_marker( $f ) . $this->tip_toggle( $f, $id ) . '</span>' . self::price_pill( $f ) . '</legend>'; // phpcs:ignore WordPress.Security.EscapeOutput
			echo $this->tip_panel( $f, $id ); // phpcs:ignore WordPress.Security.EscapeOutput
			$this->render_input( $f );
			echo '</fieldset>';
		} elseif ( 'price' === $type ) {
			echo '<span class="apo-field__label"><span class="apo-field__label-text">' . esc_html( $this->label_text( $f ) ) . $this->tip_toggle( $f, $id ) . '</span>' . self::price_pill( $f ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput
			echo $this->tip_panel( $f, $id ); // phpcs:ignore WordPress.Security.EscapeOutput
			$this->render_input( $f );
		} else {
			printf(
				'<label class="apo-field__label" for="%s"><span class="apo-field__label-text">%s%s%s</span>%s</label>',
				esc_attr( $fid ),
				esc_html( $this->label_text( $f ) ),
				$this->required_marker( $f ), // phpcs:ignore WordPress.Security.EscapeOutput
				$this->tip_toggle( $f, $id ), // phpcs:ignore WordPress.Security.EscapeOutput
				self::price_pill( $f ) // phpcs:ignore WordPress.Security.EscapeOutput
			);
			echo $this->tip_panel( $f, $id ); // phpcs:ignore WordPress.Security.EscapeOutput
			$this->render_input( $f );
		}
		echo '</div>';
	}

	private function render_input( array $f ): void {
		$id       = (string) $f['id'];
		$name     = 'apo[' . $id . ']';
		$fid      = 'clpo_' . $id;
		$type     = (string) $f['type'];
		$required = ! empty( $f['required'] );
		$req      = $required ? ' required aria-required="true"' : '';
		$rreq     = $required ? ' required' : '';
		$default  = (string) ( $f['default'] ?? '' );
		$ph       = ( '' !== (string) ( $f['placeholder'] ?? '' ) ) ? sprintf( ' placeholder="%s"', esc_attr( (string) $f['placeholder'] ) ) : '';
		$descby   = ( '' !== (string) ( $f['description'] ?? '' ) ) ? sprintf( ' aria-describedby="%s"', esc_attr( 'clpo_desc_' . $id ) ) : '';

		switch ( $type ) {
			case 'textarea':
				$ml = ! empty( $f['maxLength'] ) ? sprintf( ' maxlength="%d"', (int) $f['maxLength'] ) : '';
				printf( '<textarea id="%s" name="%s" rows="3"%s%s%s%s>%s</textarea>', esc_attr( $fid ), esc_attr( $name ), $req, $ph, $ml, $descby, esc_textarea( $default ) ); // phpcs:ignore WordPress.Security.EscapeOutput
				if ( '' !== $ml ) {
					echo '<span class="apo-counter" data-apo-counter aria-live="polite" aria-atomic="true"></span>';
				}
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
			case 'email':
			case 'url':
			case 'time':
			case 'phone':
				$html_type = 'phone' === $type ? 'tel' : $type;
				$val       = ( '' !== $default ) ? sprintf( ' value="%s"', esc_attr( $default ) ) : '';
				printf( '<input type="%s" id="%s" name="%s"%s%s%s%s />', esc_attr( $html_type ), esc_attr( $fid ), esc_attr( $name ), $req, $ph, $descby, $val ); // phpcs:ignore WordPress.Security.EscapeOutput
				break;
			case 'checkbox':
				$chk = ( 'yes' === $default ) ? ' checked' : '';
				printf( '<input type="checkbox" id="%s" name="%s" value="yes"%s%s%s />', esc_attr( $fid ), esc_attr( $name ), $req, $descby, $chk ); // phpcs:ignore WordPress.Security.EscapeOutput
				break;
			case 'radio':
			case 'buttons':
				foreach ( (array) $f['options'] as $opt ) {
					$chk = ( (string) $opt === $default ) ? ' checked' : '';
					printf( '<label class="apo-opt%s"><input type="radio" name="%s" value="%s"%s%s /> <span>%s</span></label>', 'buttons' === $type ? ' apo-btnopt' : '', esc_attr( $name ), esc_attr( $opt ), $rreq, $chk, esc_html( $opt ) ); // phpcs:ignore WordPress.Security.EscapeOutput
				}
				break;
			case 'select':
				printf( '<select id="%s" name="%s"%s%s><option value="">%s</option>', esc_attr( $fid ), esc_attr( $name ), $req, $descby, esc_html__( 'Choose an option', 'corelabs-product-options' ) ); // phpcs:ignore WordPress.Security.EscapeOutput
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
			case 'quantity':
				$min = ( '' !== (string) ( $f['min'] ?? '' ) ) ? sprintf( ' min="%s"', esc_attr( (string) $f['min'] ) ) : ' min="0"';
				$max = ( '' !== (string) ( $f['max'] ?? '' ) ) ? sprintf( ' max="%s"', esc_attr( (string) $f['max'] ) ) : '';
				$val = ( '' !== $default ) ? esc_attr( $default ) : '0';
				echo '<span class="apo-qty">';
				echo '<button type="button" class="apo-qty__btn" data-apo-step="-1" aria-hidden="true" tabindex="-1">−</button>';
				printf( '<input type="number" id="%s" name="%s" inputmode="numeric" step="1"%s%s%s%s value="%s" />', esc_attr( $fid ), esc_attr( $name ), $req, $min, $max, $descby, $val ); // phpcs:ignore WordPress.Security.EscapeOutput
				echo '<button type="button" class="apo-qty__btn" data-apo-step="1" aria-hidden="true" tabindex="-1">＋</button>';
				echo '</span>';
				break;
			case 'image_swatch':
				echo '<div class="apo-swatches apo-swatches--image">';
				foreach ( (array) $f['options'] as $opt ) {
					$opt_label = is_array( $opt ) ? (string) ( $opt['label'] ?? '' ) : (string) $opt;
					$opt_image = is_array( $opt ) ? (string) ( $opt['image'] ?? '' ) : '';
					$chk       = ( $opt_label === $default ) ? ' checked' : '';
					// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- all parts escaped below; $rreq/$chk internal fragments.
					printf(
						'<label class="apo-swatch apo-swatch--image" title="%1$s"><input type="radio" name="%2$s" value="%1$s"%3$s%5$s aria-label="%1$s" />%4$s<span class="apo-swatch__label">%1$s</span></label>',
						esc_attr( $opt_label ),
						esc_attr( $name ),
						$rreq,
						'' !== $opt_image ? '<img class="apo-swatch__img" src="' . esc_url( $opt_image ) . '" alt="" />' : '<span class="apo-swatch__dot"></span>',
						$chk
					);
					// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
				}
				echo '</div>';
				break;
			case 'file':
				$maxf = min( 10, max( 1, (int) ( $f['maxFiles'] ?? 1 ) ) );
				$mult = $maxf > 1 ? sprintf( ' multiple data-apo-max-files="%d"', $maxf ) : '';
				echo '<span class="apo-file">';
				printf( '<input type="hidden" name="%s" value="" />', esc_attr( $name ) );
				printf( '<input type="file" id="%s" class="apo-file-picker"%s%s%s />', esc_attr( $fid ), $req, $mult, $descby ); // phpcs:ignore WordPress.Security.EscapeOutput
				echo '<span class="apo-file-list"></span>';
				echo '<span class="apo-file-status" aria-live="polite"></span>';
				echo '</span>';
				break;
			case 'price':
				printf( '<input type="hidden" name="%s" value="yes" />', esc_attr( $name ) );
				if ( 'formula' !== ( $f['priceMode'] ?? 'fixed' ) && (float) ( $f['price'] ?? 0 ) > 0 ) {
					// Formula surcharges show live in the breakdown box instead.
					$fee = function_exists( 'wc_price' ) ? wp_strip_all_tags( wc_price( (float) $f['price'] ) ) : number_format( (float) $f['price'], 2 );
					printf( '<span class="apo-fee">+%s</span>', esc_html( $fee ) );
				}
				break;
			default: // text
				$ml  = ! empty( $f['maxLength'] ) ? sprintf( ' maxlength="%d"', (int) $f['maxLength'] ) : '';
				$val = ( '' !== $default ) ? sprintf( ' value="%s"', esc_attr( $default ) ) : '';
				printf( '<input type="text" id="%s" name="%s"%s%s%s%s%s />', esc_attr( $fid ), esc_attr( $name ), $req, $ph, $ml, $descby, $val ); // phpcs:ignore WordPress.Security.EscapeOutput
				if ( '' !== $ml ) {
					echo '<span class="apo-counter" data-apo-counter aria-live="polite" aria-atomic="true"></span>';
				}
		}
	}
}
