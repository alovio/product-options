# Alovio Product Options 2.0 — Design

**Date:** 2026-07-04 · **Status:** approved by user (brainstorming session)
**Repo:** `~/woo-product-options` · **Live:** wp.org slug `corelabs-product-options` v1.0.0

## 1. Goal

Take the plugin from MVP to a real product in one major release (**2.0.0**):

1. **100% free** — remove the Pro gate; every feature ships in the wp.org plugin (same move as Alovio Checkout Fields 1.2.x).
2. **Global option groups** — build once, apply to all products / categories / specific products.
3. **Builder = Checkout Fields builder** — adopt the Alovio family builder verbatim; only plugin-specific content differs.
4. **New field types & pricing modes** — 17 field types, 5 pricing modes (incl. per-character and formula).
5. **Storefront upgrade** — price breakdown box + polish pack.

Non-goals: license server, Pro add-on continuation (code-heaven listing's fate is a business decision outside this codebase), cross-group conditional logic, block-editor product form integration beyond what works today.

## 2. Business / free-ification

- Delete `includes/Pro/ProModule.php` and the `corelabs-product-options-pro/` unlocker plugin from the build.
- Inline the Pro defaults everywhere the gate filters were read:
  - operators: `is, is_not, contains, gt, lt` (always)
  - multi-conditions: always on
  - price modes: all (see §7)
  - field types: full set (see §6)
- Keep the filters (`clpo_field_types`, `clpo_allowed_operators`, `clpo_price_modes`, …) as public extension points — they now filter the FULL default set. `clpo_is_pro` disappears; an active legacy Pro unlocker plugin (`add_filter('clpo_is_pro','__return_true')`) is harmless because nothing reads it.
- Builder no longer has any `isPro` branching (`window.CLPO_BUILDER.isPro` removed).

## 3. Architecture — "Full app" (hub SPA + slim metabox)

### 3.1 Entity model

One entity: **Option Group**, stored as a hidden CPT `alovio_option_group`
(`show_ui => false`, no front-end routes; capabilities `manage_woocommerce`).

Post fields used: `post_title` (group name), `post_status` (`publish` = Active, `draft` = Draft).
Post meta:

| Meta key | Content |
|---|---|
| `_clpo_fields` | JSON — the field array (existing schema, extended per §6/§7) |
| `_clpo_assignment` | JSON — `{ "mode": "all" \| "categories" \| "products", "ids": int[] }` |
| `_clpo_priority` | int — render order when several groups hit one product (default 10) |

"Per-product options" is not a separate concept: it is a group with `mode=products, ids=[X]`.

### 3.2 Resolution & rendering

`GroupResolver::for_product( int $product_id ): array` — returns all **published** groups whose assignment matches the product (all / any assigned category incl. ancestors / explicit product id), ordered by priority then title. Result cached per-request; invalidated on group save (`clean_post_cache` + a `wp_cache` group key bump).

Storefront renders each matching group as its own `.apo-options` block (existing renderer, one `<script class="apo-rules">` per group). Conditional logic stays **scoped within a group** — the engine is untouched. Cart/validation paths iterate all matching groups.

### 3.3 Admin surfaces

- **Hub**: WooCommerce submenu **"Product Options"** → full-screen React SPA (hash router):
  - `#/groups` — list (name, status, field count, priced-field count, assignment summary) + New/Duplicate/Delete
  - `#/groups/{id}` — the builder (§5)
  - `#/templates` — starter templates (§8)
  - `#/settings` — uninstall-data toggle (existing option), future home for misc settings
- **Product metabox** (replaces the current builder metabox): read-only summary — list of groups applying to this product with **"Edit ↗"** deep links (`admin.php?page=alovio-product-options#/groups/{id}`), plus **"Create options for this product"** → POST creates a draft group pre-assigned to this product, then redirects into the builder.

### 3.4 REST API (`clpo/v1`, capability `manage_woocommerce` + nonce)

| Route | Methods | Purpose |
|---|---|---|
| `/groups` | GET, POST | list / create |
| `/groups/{id}` | GET, PUT, DELETE | read / update (fields+assignment+title+status+priority) / delete |
| `/groups/{id}/duplicate` | POST | copy |
| `/export` · `/import` | GET · POST | JSON round-trip (§8) |
| `/products/search?q=` | GET | product picker for assignment UI |
| `/product/{id}/fields` | GET | **kept, read-only** — powers the metabox summary; POST is removed |

### 3.5 Migration (1.x → 2.0)

Version-gated routine on `admin_init` when `get_option('clpo_version') < 2.0`:

1. For every product with non-empty `_clpo_field_group` meta → create CPT group: title "*{product name} — Options*", fields = meta JSON (normalized), assignment `{mode:products, ids:[product]}`, status publish.
2. Original meta is **left in place** (rollback safety). Renderer/cart read **CPT-only** in 2.0; the meta is dead data.
3. Idempotent: mark migrated products (`_clpo_migrated_to` = group id) and skip on re-run. Set `clpo_version`.

Uninstall (`uninstall.php`, still opt-in via `clpo_remove_data_on_uninstall`): also delete `alovio_option_group` posts + their meta + `_clpo_migrated_to`.

## 4. Frontend contracts that DO NOT change

- Form field name `apo[<field_id>]`, `$_POST['apo']` reading, cart-item key `'apo'` (session only), order item meta = human label → display value.
- CSS class prefix `apo-*`, `data-apo-*` attributes, `<script class="apo-rules">` hydration.
- PHP↔JS conditional-logic engine and `tests/fixtures/conditional-cases.json` parity harness.
- Server stays authoritative for all money math.

Cart-item internals gain `group_id` per options bundle: `$cart_item_data['apo']` becomes a **list** of per-group entries `{group_id, options, base_price, unique_key}` — computed once in `add_cart_item_data`, consumed by the same recalculate/display/order hooks (iterate the list).

## 5. Builder — adopt Checkout Fields verbatim

Port `~/woo-checkout-fields/src/builder/*` + `assets/css/builder.css` with `clcf→clpo` renames. The hub SPA wraps the builder with the router/list screens.

**Identical (no redesign):** `AppShell` (dark Alovio header, status pill, Undo, ⌘S, Save & publish), `Palette`, `PreviewCanvas` + `FieldPreview` frame, `SimulationBar` mechanism, `SettingsPanel` tab shell, store/reducer with undo history, all design tokens.

**Adapted content:**

| CF piece | 2.0 version |
|---|---|
| `FieldPreview` type renderers | product-option types (§6) incl. swatches, buttons, file, quantity |
| `panels/Fee.jsx` | `panels/Pricing.jsx` — mode picker (§7) + live example line |
| `panels/Logic.jsx` sources | sibling fields only (no `@context` tokens in v2.0) |
| `SimulationBar` cart presets | field-value simulation: set any field's value, watch show/hide + running total |
| — (new) | `panels/Assignment.jsx` — mode radio + category/product pickers + priority |
| — (new) | list / templates / settings screens + hash router |

Save = PUT `/groups/{id}` (fields + assignment together; one Save & publish button, publish flips post_status).

## 6. Field types (17)

| Origin | Types |
|---|---|
| Existing free | text, textarea, number, checkbox, radio, select, price (surcharge), heading |
| Pro → free | swatch (colour), date |
| Ported from Checkout Fields 1.2.x | email, phone, url, time, **file** (secure upload: allowlist ext/size, obfuscated dir, admin-only access) |
| New to family | **image swatch** (options = {label, image_id/url}, media-library picker in builder), **button group** (options = text buttons, single-select), **quantity** (stepper UI, integer min/max/step) |

All new types get: OptionSanitizer case, Validator case, ProductFormRenderer case, FieldPreview renderer, JS `readValues` support where input shape differs (file/quantity), and order-meta display formatting (file → admin download link, image swatch → label).

## 7. Pricing modes (5)

`priceMode` per field: `fixed` | `per_unit` (number/quantity) | `percent` (of purchased entity's base price) | **`per_char`** (text/textarea: `price × trimmed length`) | **`formula`**.

**Formula mode:** expression evaluated over group-field tokens, e.g. `{width} * {height} * 0.85`.
- Engine: port **Alovio Calculator's** decimal-safe expression evaluator (PHP + JS mirror; shunting-yard, no eval). Grammar: numbers, `+ - * /`, parentheses, `{field_id}` tokens (numeric value of engaged fields, else 0), `min() max() round()`.
- Authoritative on server in `PriceCalculator::addon_total`; mirrored client-side for the live breakdown. Parity via a new `tests/fixtures/formula-cases.json`.
- Builder: formula textarea with token insert buttons + live validation (parse errors shown inline).

`PriceCalculator` continues to return a rounded total; it additionally exposes a per-field **breakdown array** `{field_id, label, amount}` for §9 and for cart display reuse.

## 8. Templates & import/export

- **Export**: one group or all → JSON `{version, groups:[{title, fields, assignment, priority}]}`.
- **Import**: same JSON; ids regenerated; imported as drafts; schema-normalized (unknown types/modes dropped with a notice).
- **Templates**: 6 built-in JSON presets shipped in `includes/Templates/` — Gift options, Engraving, T-shirt designer (image swatch + buttons), Made-to-order dimensions (formula), Delivery date, Donation/tip. "Use template" = import as draft + open builder.

## 9. Storefront

**Breakdown box (chosen design B):** replaces the single "Options total" line whenever ≥1 priced field is engaged. Rows: base price, each engaged priced option (label + formatted amount), dashed-rule **Total** row (base + options). Live client-side updates (aria-live polite); server renders initial state. Ships in `frontend.css`, inherits theme fonts/colors, uses existing currency-format helper.

**Polish pack (all themes, no options):** price suffix on labels/choices ("Gift wrap **(+$8.00)**"), tooltip/help icon rendering for field description (`?` toggle, keyboard accessible), character counter on max-length text fields (`14 / 40`), inline required validation on blur/submit (message under field, `aria-invalid` + `aria-describedby` — no more silent scroll-to-top), focus rings, upgraded swatch/date/file/quantity styling, RTL parity.

## 10. Testing

- Keep all 57 PHP + 23 JS green through the refactor.
- New unit coverage: GroupResolver (all/category/product/priority/draft), migration (idempotency, meta preservation), sanitizer/validator/pricing per new type, per_char + formula math, formula PHP↔JS fixture parity, import schema-normalization.
- Builder: extend CF's jest patterns to new panels (Pricing/Assignment reducers).
- Live QA (wp-env + Playwright): money-path with a global group + a product group on one product (breakdown box math), file upload end-to-end, migration from a seeded 1.0 site, metabox deep links.

## 11. Release (2.0.0)

readme rewrite ("100% free", new feature set, new FAQ) · fresh screenshots (hub, builder, breakdown box) · `.pot` regen · Tested-up-to bump · changelog · `build-dist.sh` drops the Pro zip step · SVN trunk + `tags/2.0.0` + new screenshots to assets · GitHub push (private) · demo site (`demo.alovio.org/woo`) update · alovio.org store copy already lists the plugin as free.

## 12. Risks & mitigations

- **Metabox → hub jump feels indirect** → deep links + "Create options for this product" one-click path (§3.3).
- **Multiple groups on one product** (new surface) → priority ordering + per-group logic scoping + live QA case (§10).
- **Formula abuse/perf** → strict grammar, parse-time validation, length cap (200 chars), no functions beyond min/max/round.
- **File uploads** → reuse CF's hardened implementation verbatim (allowlist, size cap, obfuscated storage, no direct listing).
- **Migration surprises** → idempotent + original meta untouched; QA on a seeded 1.0 site before release.
