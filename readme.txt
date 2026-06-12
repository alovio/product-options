=== Alovio Product Options for WooCommerce ===
Contributors: 74h1r
Tags: product options, extra fields, add-ons, conditional logic, product addons
Requires at least: 6.4
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Add custom product fields with conditional logic and add-on pricing to your store. Drag-and-drop builder, live preview, and a running options total.

== Description ==

**Alovio Product Options for WooCommerce** lets you add extra fields and paid add-ons to your products, then show, hide, or require those fields based on what the customer chooses. Build forms visually with a drag-and-drop builder, preview them live, and let shoppers see a running, currency-formatted options total before they add to cart.

Whether you sell customizable products, gifts with engraving, made-to-order items, or services with optional extras, you can collect exactly the information you need and charge for it accurately.

= Field types included (free) =

* **Text** — single-line input with placeholder and max-length.
* **Text area** — multi-line input for longer notes.
* **Number** — with min, max, and step controls.
* **Checkbox** — a single yes/no option.
* **Multiple choice (radio)** — pick one from a list of options.
* **Dropdown (select)** — choose one option from a menu.
* **Surcharge / fee** — add a fixed charge to the order.
* **Section heading** — group and label your fields.

= Conditional logic (free) =

Show, hide, or require any field based on the value of another field using a single condition with the **is** and **is not** operators. Keep your product forms clean by only revealing the fields that are relevant to each customer's selection.

= Add-on pricing (free) =

Attach a flat add-on price to options so the cost of customizations is reflected automatically. The product page shows a live, currency-formatted **options total** that updates as customers make selections, so there are no surprises at checkout.

= Builder and field settings (free) =

* Drag-and-drop builder with **live preview**.
* Per-field **placeholder**, **help / description text**, and **default value**.
* **Min, max, and step** for number fields.
* **Max-length** for text fields.

= Built right =

* **HPOS compatible** (High-Performance Order Storage).
* **Accessible** — proper `fieldset`/`legend` structure, ARIA attributes, and visible focus styles.
* **Fully translatable** — ready for localization into any language.

= Go further with Pro =

A separate **Pro add-on** (sold separately, not on WordPress.org) extends the free plugin with:

* Advanced **multi-condition** logic and more operators.
* **Per-unit** and **percentage** pricing.
* **Colour swatches**.
* **Date** fields.

The features listed under the free sections above are all included in this plugin. Pro is optional and adds the capabilities listed in this section only.

== Installation ==

1. Make sure WooCommerce is installed and active.
2. In your WordPress admin, go to **Plugins > Add New** and search for "Alovio Product Options for WooCommerce", or upload the plugin ZIP under **Plugins > Add New > Upload Plugin**.
3. Click **Install Now**, then **Activate**.
4. Edit any product, open the **Product Options** tab, and use the drag-and-drop builder to add fields, set add-on prices, and configure conditional logic.
5. Save the product and view it on the storefront to see your fields and the live options total.

== Frequently Asked Questions ==

= Does this require WooCommerce? =

Yes. WooCommerce must be installed and active for the plugin to work.

= What field types are available in the free version? =

Text, text area, number, checkbox, multiple choice (radio), dropdown (select), surcharge/fee, and section heading.

= Can I show or hide a field based on another field? =

Yes. The free version supports single-condition conditional logic to show, hide, or require a field based on another field's value, using the "is" and "is not" operators. Advanced multi-condition logic and additional operators are available in the Pro add-on.

= How does add-on pricing work? =

The free version supports flat add-on pricing: you assign a fixed price to options, and the product page displays a running, currency-formatted options total as customers make selections. Per-unit and percentage pricing are available in the Pro add-on.

= Is the plugin compatible with HPOS (High-Performance Order Storage)? =

Yes. The plugin is compatible with WooCommerce's High-Performance Order Storage.

= Is the plugin accessible? =

Yes. Forms use proper `fieldset` and `legend` structure, ARIA attributes, and visible focus styles to support keyboard and screen-reader users.

= Can I translate the plugin? =

Yes. The plugin is fully translatable and ready for localization.

= Do I need Pro to use the plugin? =

No. The free plugin is fully functional on its own. Pro is an optional, separately sold add-on that adds multi-condition logic, more operators, per-unit and percentage pricing, colour swatches, and date fields.

== Screenshots ==

1. The drag-and-drop builder with live preview, showing fields and single-condition conditional logic settings.
2. The product page with custom options and the live, currency-formatted options total.

== Development ==

This plugin is not obfuscated. The compiled assets in `build/` are generated from the human-readable source shipped in this package:

* `src/` — the admin builder (React, via `@wordpress/scripts`) and the product-page runtime (vanilla JS) that compile to `build/index.js` and `build/frontend.js`.
* `assets/css/` — the stylesheet sources compiled to `build/index.css` and `build/frontend.css`.
* `composer.json` — the PSR-4 autoload map for the `includes/` PHP classes.

To rebuild from source:

`npm install` then `npm run build` (uses `@wordpress/scripts` / webpack; see `webpack.config.js`).
`composer install` regenerates the PHP autoloader.

== Changelog ==

= 1.0.0 =
* Initial release.
* Field types: text, text area, number, checkbox, multiple choice (radio), dropdown (select), surcharge/fee, and section heading.
* Single-condition conditional logic (show / hide / require) with "is" and "is not" operators.
* Flat add-on pricing with a live, currency-formatted options total.
* Drag-and-drop builder with live preview.
* Per-field placeholder, help/description text, default value, number min/max/step, and text max-length.
* HPOS compatibility.
* Accessibility: fieldset/legend structure, ARIA attributes, and focus styles.
* Fully translatable.
