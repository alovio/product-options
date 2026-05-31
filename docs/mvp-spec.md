# WooCommerce Extra Product Options — MVP Spec

Working name: **Advanced Product Options for WooCommerce** (brand name provisional).
Model: freemium (wordpress.org free) + Freemius Pro, annual + monthly.
Strategy: A+B — message = "free conditional logic"; pillar = modern drag-drop builder + live preview.

## Target users
Stores needing product customization: engraving/print, gift boxes, size-based pricing
(curtains, frames), catering, handmade, B2B configuration. No money handling, no deliverability → low risk.

## Free vs Pro split

### FREE (wordpress.org) — win installs
- Field types: text, textarea, number, checkbox, radio, select, simple fixed-fee price add-on
- Per-product fields
- **Basic conditional logic** (show/hide a field based on ONE other field's value) ← the hook
- **Modern visual builder** (drag-drop + live preview) ← UX pillar (kept in free to feel premium, drive reviews)
- Cart + order display, required fields, basic validation

### PRO (Freemius) — revenue
- Advanced conditional logic: multiple conditions (AND/OR), conditions on qty/price/cart total, multiple actions
- Dynamic pricing / formulas (e.g. width × height × rate), percentage fees, quantity-based
- Advanced fields: file upload, date/time picker, color/image swatch, image-select, range slider, signature
- Global add-on groups (apply by category/tag/rule)
- Multi-step (wizard) forms, min/max constraints, styling/Google Fonts
- Import/export, priority support

Split logic: free is useful AND owns the headline hook (conditional logic + nice builder) → installs;
Pro gates the power features serious stores need (formulas, file upload, swatches, advanced conditions) → revenue.

## MVP v1 scope (ship fast)
- **v1 Free:** 6–7 field types, per-product fields, builder w/ live preview, single-condition logic,
  fixed price add-on, cart/order display, validation.
- **v1 Pro (thin):** multi-condition logic (AND/OR), 3–4 advanced fields (file, date, color/image swatch),
  basic price formula, category-level global add-ons.
- **Defer to v2+:** wizard, repeater, signature, page-builder deep integrations, import/export.

## Conditional logic engine
Rule = IF [field] [operator] [value] THEN [show|hide|require] [target field].
- Free: single condition, one target.
- Pro: multiple conditions with AND/OR groups, numeric operators (>, <, between),
  conditions on cart qty/total, multiple actions.

## Visual builder
Left = field palette (drag types). Center = canvas (drag-reorder, click-to-edit).
Right = field settings + conditional-rule editor. Live-preview tab renders the actual product-page output.
Built with React via `@wordpress/components` for native feel; REST endpoints to save configs.

## Technical architecture
- Storage: field definitions as JSON in product meta; global groups in a dedicated CPT.
- Render: hook `woocommerce_before_add_to_cart_button`.
- Cart flow: `woocommerce_add_cart_item_data` (data) → `woocommerce_before_calculate_totals` (price)
  → `woocommerce_get_item_data` (display) → `woocommerce_checkout_create_order_line_item` (persist to order).
- **HPOS compatible:** declare compatibility, use CRUD APIs, no direct order-table SQL.
- Price formula (Pro): safe expression parser — NO `eval` (financial correctness).
- i18n-ready; translation-loaded.

## Pricing (default — adjustable)
- Free on wordpress.org.
- Pro (Freemius): **$49/yr** (1 site) · **$99/yr** (5) · **$179/yr** (25). Monthly option (~$6–7/mo).
- No lifetime license (protects recurring). Optional renewal discount.

## Differentiation vs incumbents
| | Studio Wombat / Plugin Republic / Acowebs | Us |
|---|---|---|
| Conditional logic | mostly Pro-gated | **in Free (basic)** |
| Builder UX | functional but dated | drag-drop + live preview |
| Price | $49–119/yr | market-aligned |

## Hard parts / risk
1. Theme/page-builder compatibility (product-page markup varies) — biggest support burden.
2. Price recalculation edge cases (tax/currency/discounts) — must be correct (trust).
3. Builder UX — real front-end engineering (the cost of pillar B).
4. Freemium 3–4% conversion → install volume essential (wp.org SEO + content).

## Roadmap (post-v1)
Multi-step forms → more field types → page-builder integrations → bundles/marketing.

## Go-to-market
wp.org SEO ("woocommerce product addons", "conditional logic") · "X vs Y" comparison pages ·
"free conditional logic" outreach hook · existing customer base.
