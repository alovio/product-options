=== Alovio Product Options – Extra Product Fields & Add-Ons for WooCommerce ===
Contributors: 74h1r
Tags: product options, extra fields, add-ons, conditional logic, woocommerce
Requires at least: 6.4
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 2.3.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

18 field types, conditional logic, a price per option, formula pricing and a live price breakdown — a complete product configurator, 100% free.

== Description ==

**Alovio Product Options** turns WooCommerce products into configurable products. Add extra fields and paid add-ons, show or hide them with conditional logic, price them per unit, per character, as a percentage or with a formula — and let shoppers watch a live price breakdown update as they choose.

Everything is free. No Pro version, no locked features, no upsell screens.

= Build once, apply everywhere =

Create an **option group** in the visual builder and apply it to **all products**, whole **categories**, or hand-picked products. Priorities control the order when several groups meet on one product. A slim panel on the product-edit screen shows exactly which groups apply, with one-click deep links.

= 18 field types =

Text, text area, number, checkbox, multiple choice (radio), dropdown, **button group**, **colour swatch**, **image swatch**, **quantity stepper**, date, time, email, phone, URL, **file upload** (secure, rate-limited, MIME-verified — up to 10 files per field, each removable), surcharge, and section heading.

= Conditional logic — all of it free =

Show, hide or require any field based on other fields, with **multi-condition AND/OR rules** and five operators (*is, is not, contains, greater than, less than*). The builder previews rules live: set a value in the simulation bar and watch fields appear and disappear exactly as they will on the product page.

= A price per option =

Selling sizes, materials or finishes? Give **every choice its own price** — *21x30 +399, 30x40 +499, 50x70 +799* — in dropdowns, radio buttons, button groups, colour and image swatches. Prices appear beside each choice automatically, so shoppers compare before they pick.

= Five pricing modes =

* **Fixed** — a flat add-on price.
* **Per unit** — price × the number entered (number and quantity fields).
* **Per character** — price × text length (engraving!).
* **Percentage** — a share of the product price, variation-aware.
* **Formula** — decimal-safe expressions over your fields: `{width} * {height} * 0.85`, with `min()`, `max()`, `round()`.

= Live price breakdown =

Instead of a mystery total, shoppers see an itemized box: base price, each chosen option with its cost, and the final total — updating live, following the selected variation, formatted in your store currency.

= A builder you'll actually enjoy =

The full-screen builder shows your fields **as the customer sees them**, with drag-and-drop ordering, undo, ⌘S save, a preview-as-customer simulation bar and a live options total. Start from one of **six templates** — gift options, engraving, t-shirt designer, made-to-order dimensions, delivery date, donation — or import/export groups as JSON between sites.

= Built right =

* **HPOS compatible** (High-Performance Order Storage).
* **Accessible** — fieldset/legend structure, ARIA attributes, focus styles, inline validation messages.
* **Translatable & RTL-ready.**
* Option values flow into cart, checkout, order emails and the admin order screen — each priced option shows its cost right on the cart line ("Gift wrap: Yes (+$5.00)") — and uploads become admin download links.
* Server-side prices are always authoritative — the browser preview can never change what gets charged.

== Installation ==

1. Make sure WooCommerce is installed and active.
2. Go to **Plugins → Add New**, search for "Alovio Product Options", install and activate.
3. Open **WooCommerce → Product Options** and create your first group — or start from a template.
4. Assign the group to all products, categories or specific products, hit **Save & publish**, and check the product page.

Upgrading from 1.x? Your per-product fields are migrated to option groups automatically — nothing to do.

== Frequently Asked Questions ==

= Is it really all free? =

Yes. As of 2.0 every feature — multi-condition logic, all field types, all pricing modes, file uploads, templates, import/export — ships in this plugin. There is no Pro version.

= Does this require WooCommerce? =

Yes. WooCommerce must be installed and active.

= Can I apply the same options to many products? =

Yes — that's the core of 2.0. Assign a group to all products, one or more categories (child categories included), or specific products. Multiple groups can apply to one product.

= Can each choice in a dropdown have a different price? =

Yes. In the builder's **Options** tab every choice has its own **Price** box — leave it empty to fall back to the field price. Works for dropdowns, radio buttons, button groups, and colour and image swatches, and the amount shows beside the choice on the product page.

= Can I charge based on a calculation? =

Yes. The formula pricing mode evaluates decimal-safe expressions over your number and quantity fields, e.g. `round({width} * {height} * 0.02, 2)`.

= Does the price follow product variations? =

Yes. The breakdown box tracks the selected variation's price, and percentage-mode options recalculate from it. The server prices from the variation too.

= How do file uploads work? =

Files upload immediately on selection to a randomized, listing-protected folder after extension and real MIME checks, with a size cap and per-IP rate limiting. The cart shows the file names; the order stores an admin download link per file. Abandoned uploads are cleaned up automatically.

= Can customers upload more than one file? =

Yes — set **Max files** on the file field (up to 10). Customers see each uploaded file as a removable item, so they can delete or replace any file individually before adding to cart. Allowed formats out of the box: JPG, PNG, WEBP and PDF (filterable via `clpo_upload_extensions`).

= Is the plugin compatible with HPOS? =

Yes, fully.

= Can I move option groups between sites? =

Yes — export any group (or all of them) as JSON and import on another site. Imports arrive as drafts and are schema-checked with clear warnings.

== Screenshots ==

1. The Product Options hub — every option group with its status and assignment, one click from editing.
2. The full-screen builder: 18-type palette, live product preview with a preview-as-customer simulation bar, and the formula pricing editor.
3. Six starter templates — gift options, engraving, t-shirt designer, made-to-order, delivery date and donation.
4. The storefront: option cards light up as customers choose — price pills, colour swatches and a quantity stepper.
5. The live price breakdown — base price, each chosen option and the total, right above Add to cart.

== Changelog ==

= 2.3.0 =
* NEW: Per-option pricing — each choice in a dropdown, radio, button group, colour or image swatch can charge its own amount (21x30 +399, 30x40 +499, 50x70 +799). Set it in the builder's Options tab.
* NEW: Option prices show beside each choice on the product page automatically ("50x70 (+$799.00)"), and the field's pill summarises the span ("+$399.00 – $799.00").
* Improve: option prices run through the field's pricing mode, so "10% Express / 20% Overnight" works exactly like flat amounts.
* Fix: currency markup could leak a literal "&#36;" into option labels on themes that skip wptexturize.

= 2.2.0 =
* NEW: The cart and checkout now show each option's price on the line item as a price chip — "Gift wrap: Yes  +$5.00" — with per-unit, per-character, percentage and formula modes all resolving to their real amounts.
* NEW: Surcharge fields get their own cart row (e.g. "Board fee: +$48.00"), so the whole line price is explained — no more silent fees.
* Improve: the Block cart squeezed every option into one long slash-separated line; options now render one per line with bold labels — in the cart, checkout and mini-cart, styled like the product page's price pills.
* Both work on the classic and the Block (Store API) cart; disable via the `clpo_cart_option_prices` and `clpo_cart_option_styles` filters.

= 2.1.0 =
* NEW: Multi-file upload — file fields get a **Max files** setting (1–10); customers can upload several files to one field, shown as a list with a remove button per file.
* NEW: WEBP joins the default allowed upload formats (JPG, PNG, WEBP, PDF — still filterable via `clpo_upload_extensions`).
* Improve: single-file fields get the same removable file item, so a chosen file can be deleted or replaced before add to cart.
* Improve: every uploaded file gets its own download link in the order details and admin order screen.
* Fix: an inline "required" message could stick after the file finished uploading; validation now re-runs as soon as the field changes.
* Fix: the file picker could overflow its option card on themes without a global border-box reset.

= 2.0.1 =
* Fix: the selected option in a button group could render as white text on a white fill on some themes — selected buttons now show clearly.
* Fix: a field's group label (e.g. a swatch or button title) could sit outside its option card on themes that give fieldset legends a negative margin (such as Storefront); labels now align with the field.
* Fix: the description "?" toggle rendered on its own line instead of beside the field label.
* Improve: a single checkbox option is now a styled control that matches the swatch/button design, instead of a raw browser checkbox.

= 2.0.0 =
* Everything is free: the Pro gate is gone — multi-condition logic, colour swatches, date fields, per-unit and percentage pricing now ship in the plugin.
* NEW: Global option groups — build once, apply to all products, categories or specific products, with priorities.
* NEW: Full-screen builder hub (WooCommerce → Product Options) with a live product preview, preview-as-customer simulation, undo and ⌘S save.
* NEW field types: image swatch, button group, quantity stepper, file upload, email, phone, URL, time.
* NEW pricing modes: per-character and decimal-safe formula (`{width} * {height} * 0.85` with min/max/round).
* NEW: Live price breakdown box on the product page — itemized, currency-formatted, variation-aware.
* NEW: Six starter templates and JSON import/export for option groups.
* Storefront polish: prices on labels, help tooltips, character counters, inline validation, focus styles, RTL.
* 1.x per-product fields migrate to option groups automatically; carts survive the upgrade.

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 2.3.0 =
Per-option pricing: every choice in a dropdown, radio, button group or swatch can now charge its own amount, shown beside the option and in the cart.

= 2.2.0 =
The cart now shows each option's price on the line item ("Gift wrap: Yes (+$5.00)") and lists surcharges explicitly — on both classic and Block carts.

= 2.1.0 =
Multi-file uploads (up to 10 per field, individually removable), WEBP upload support, per-file order download links, and two small storefront fixes.

= 2.0.1 =
Storefront polish fixes: the selected button option, group-label alignment, the description toggle position, and the single-checkbox style.

= 2.0.0 =
Major release: everything is now free — global option groups, 18 field types, formula pricing, live price breakdown. 1.x data migrates automatically.

== Development ==

This plugin is not obfuscated. The compiled assets in `build/` are generated from the human-readable source shipped in this package:

* `src/` — the admin hub/builder (React, via `@wordpress/scripts`), the product-page runtime and the shared formula engine, compiled to `build/hub.js` and `build/frontend.js`.
* `assets/css/` — stylesheet sources compiled into the bundles.
* `composer.json` — the PSR-4 autoload map for the `includes/` PHP classes.

To rebuild from source: `npm install && npm run build` (webpack via `@wordpress/scripts`; see `webpack.config.js`), and `composer install` for the PHP autoloader.
