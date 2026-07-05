# Alovio Product Options 2.0 Implementation Plan

> **For agentic workers:** REQUIRED: Use superpowers:subagent-driven-development (if subagents available) or superpowers:executing-plans to implement this plan. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ship v2.0.0 — 100% free, global option groups (CPT + resolver + migration), hub SPA reusing the Checkout Fields builder, 18 field types, 5 pricing modes (incl. formula), storefront breakdown box + polish.

**Architecture:** One entity (Option Group, hidden CPT `alovio_option_group`) resolved onto products by assignment rules; a full-screen React hub (WooCommerce submenu) hosts the ported Alovio Checkout Fields builder; the product metabox becomes a read-only summary with deep links. Server stays authoritative for money math; PHP↔JS engines stay fixture-synced.

**Tech Stack:** PHP 7.4+ (PSR-4 `CoreLabs\ProductOptions\`), WordPress/WooCommerce, `@wordpress/scripts` + `@wordpress/components`/`@wordpress/data` (React), PHPUnit + Brain Monkey, Jest, wp-env + Playwright for live QA.

**Spec:** `docs/superpowers/specs/2026-07-04-product-options-2.0-design.md` (approved). Read it before starting.

**Conventions (apply to every task):**
- Commits: stage SPECIFIC files only (never `git add -A`/`.`), identity `git -c user.name='Tahir' -c user.email='ttaxiir@gmail.com' commit …`, no Co-Authored-By lines.
- Text domain stays `corelabs-product-options`; prefixes `CLPO_`/`clpo_`/`apo-` (CSS) per spec §4.
- Test commands: `composer test` (PHP), `npm run test:js` (Jest), `npm run build` (bundles). All existing tests (57 PHP + 23 JS) must stay green after every task.
- Reference codebases (read-only): Checkout Fields `~/woo-checkout-fields` (builder + field types + FileUploads), Alovio Calculator `~/alovio-calculator` (formula engine).

## File Structure (target state)

```
includes/
  Plugin.php                     # boot wiring (modified: Pro gone, new modules)
  Groups/OptionGroupCpt.php      # NEW — CPT registration
  Groups/GroupRepository.php     # NEW — CRUD over the CPT (fields/assignment/priority JSON meta)
  Groups/GroupResolver.php       # NEW — assignment matching (pure core + WP glue)
  Setup/Migration.php            # NEW — 1.x meta → CPT groups, version-gated
  Admin/HubPage.php              # NEW — WooCommerce submenu, SPA mount (replaces builder metabox)
  Admin/ProductSummaryBox.php    # NEW — slim product metabox (summary + deep links)
  Admin/GroupsRestController.php # NEW — clpo/v1 groups/search/import/export
  Admin/RestController.php       # modified — /product/{id}/fields becomes GET-only
  Admin/BuilderAssets.php        # modified — enqueue hub SPA on the hub page only
  Fields/FieldTypes.php          # modified — full 18-type set
  Fields/FieldSchema.php         # modified — inline full operators/modes; new type/option schemas
  Cart/OptionSanitizer.php       # modified — new type cases
  Cart/Validator.php             # modified — new type cases
  Cart/CartItemShape.php         # NEW — pure cart-entry shape helpers (normalize_apo, pick_group, collect_errors)
  Cart/CartIntegration.php       # modified — hooks delegate to CartItemShape; per-group list + addon_total
  Cart/PriceCalculator.php       # modified — per_char/formula modes + breakdown()
  Cart/FileUploads.php           # NEW — ported from CF + cart lifecycle adaptations
  Formula/…                      # NEW — ported engine (Lexer/Parser/Evaluator/DecimalMath/FormulaError) + FormulaPrice facade
  Frontend/ProductFormRenderer.php # modified — multi-group render, new types, breakdown box, polish
  Frontend/FrontendAssets.php    # modified — variation events, uploader config
  Templates/*.json + Templates.php # NEW — 6 presets + loader
includes/Pro/                    # DELETED
corelabs-product-options-pro/    # DELETED
src/
  hub/ (index.js, router, screens/GroupsList|Templates|Settings) # NEW
  builder/ (ported CF: AppShell, Palette, PreviewCanvas, FieldPreview, SimulationBar,
            SettingsPanel, panels/{General,Options,Logic,Pricing,Assignment}, store, reducer, describe) # REPLACED
  frontend/ (conditional-logic.js kept; price-update.js extended; uploader.js, polish.js NEW)
  shared/formula/evaluator.js    # NEW — JS mirror of the formula engine
tests/
  php/Unit/{GroupResolverTest, MigrationTest, CartItemShapeTest, FormulaTest,
            per-type additions to OptionSanitizer/Validator/PriceCalculator tests}
  js/{formula.test.js, hub/builder reducer tests}
  fixtures/formula-cases.json    # NEW — PHP↔JS parity
```

---

## Chunk 1: Free-ification, data model, resolver, cart list-shape, migration (spec §2, §3.1–3.2, §3.5, §4)

End state: plugin is 100% free; groups live in the CPT; the storefront renders **resolved groups** (the old per-product metabox UI still writes product meta until Chunk 2, but rendering/cart already go through the resolver + migration output).

### Task 1.1: Free-ification — delete the Pro gate

**Files:**
- Delete: `includes/Pro/ProModule.php`, `corelabs-product-options-pro/` (whole dir)
- Modify: `includes/Plugin.php` (drop ProModule block), `includes/Fields/FieldSchema.php:18-21`, `includes/Admin/BuilderAssets.php:62-63`, `bin/build-dist.sh` (drop Pro zip step + excludes), `.distignore` (drop the two Pro lines)
- Test: `tests/php/Unit/ProConditionalTest.php` → rename semantics (see step 3)

- [ ] **Step 1: Delete Pro artifacts**

```bash
cd /Users/tahir/woo-product-options
git rm -r includes/Pro corelabs-product-options-pro
```

- [ ] **Step 2: Inline full defaults**

In `includes/Fields/FieldSchema.php` replace the four gate reads:

```php
$ops     = (array) apply_filters( 'clpo_allowed_operators', array( 'is', 'is_not', 'contains', 'gt', 'lt' ) );
$actions = (array) apply_filters( 'clpo_allowed_actions', array( 'show', 'hide', 'require' ) );
$multi   = (bool) apply_filters( 'clpo_multi_conditions', true );
$modes   = (array) apply_filters( 'clpo_price_modes', array( 'fixed', 'per_unit', 'percent' ) );
```

(The mode list grows in Chunk 3; only the defaults change here.) In `includes/Plugin.php` delete the `class_exists( ProModule )` block. In `includes/Admin/BuilderAssets.php` delete the `'isPro' => …` line and change `'operators'` default to the 5-operator list. In `includes/Fields/FieldTypes.php` set `FREE` → `TYPES` const `array( 'text','textarea','number','checkbox','radio','select','price','heading','swatch','date' )` (new types come in Chunk 3) and update the docblock — keep the `clpo_field_types` filter.

- [ ] **Step 3: Update tests that asserted the gate**

`tests/php/Unit/ProConditionalTest.php` currently stubs `clpo_multi_conditions=false` etc. to test the FREE limits. First rename with `git mv tests/php/Unit/ProConditionalTest.php tests/php/Unit/ExtensionFiltersTest.php` (stages both sides of the rename), rename the class to match, then update the "free tier" assertions to expect the full defaults (multi-conditions normalized, 5 operators accepted, swatch/date types kept by `FieldSchema::normalize` without a filter). Keep the filter-override tests (they now prove the extension points still work).

- [ ] **Step 4: Run tests**

Run: `composer test` — Expected: PASS (57 tests; adjusted assertions, same count ±0).
Run: `npm run test:js` — Expected: 23 pass (no JS change yet).

- [ ] **Step 5: Build + commit**

```bash
npm run build   # build/ is gitignored — compiled output is never committed in this repo
git add includes/Plugin.php includes/Fields/FieldSchema.php includes/Fields/FieldTypes.php \
  includes/Admin/BuilderAssets.php bin/build-dist.sh .distignore \
  tests/php/Unit/ExtensionFiltersTest.php
git -c user.name='Tahir' -c user.email='ttaxiir@gmail.com' commit -m "feat!: 2.0 free-ification — remove Pro gate, inline full defaults (spec §2)"
```

### Task 1.2: Option Group CPT + repository

**Files:**
- Create: `includes/Groups/OptionGroupCpt.php`, `includes/Groups/GroupRepository.php`
- Modify: `includes/Plugin.php` (wire `OptionGroupCpt`)
- Test: `tests/php/Unit/GroupRepositoryTest.php`

- [ ] **Step 1: Write failing test for repository (de)serialization**

The repository is WP-glue thin; unit-test the pure normalizers with Brain Monkey stubs for `get_post_meta`/`wp_json_encode` style calls following the existing `TestCase` pattern (see `tests/php/Unit/TestCase.php`):

```php
<?php
namespace CoreLabs\ProductOptions\Tests\Unit;

use CoreLabs\ProductOptions\Groups\GroupRepository;

final class GroupRepositoryTest extends TestCase {
	public function test_normalize_assignment_defaults_and_clamps(): void {
		$a = GroupRepository::normalize_assignment( array( 'mode' => 'bogus', 'ids' => array( '3', -1, 'x' ) ) );
		$this->assertSame( array( 'mode' => 'all', 'ids' => array() ), $a );
		$b = GroupRepository::normalize_assignment( array( 'mode' => 'products', 'ids' => array( '3', 7, 7 ) ) );
		$this->assertSame( array( 'mode' => 'products', 'ids' => array( 3, 7 ) ), $b );
	}
	public function test_group_to_array_shape(): void {
		$g = GroupRepository::to_array( 12, 'Gift options', 'publish',
			array( 'fields' => array() ), array( 'mode' => 'all', 'ids' => array() ), 10 );
		$this->assertSame( array( 'id', 'title', 'status', 'fields', 'assignment', 'priority' ), array_keys( $g ) );
	}
}
```

- [ ] **Step 2: Run to verify it fails** — `composer test` → FAIL "Class …GroupRepository not found".

- [ ] **Step 3: Implement**

`includes/Groups/OptionGroupCpt.php`:

```php
<?php
declare( strict_types=1 );

namespace CoreLabs\ProductOptions\Groups;

defined( 'ABSPATH' ) || exit;

/** Hidden storage CPT for option groups (spec §3.1). */
final class OptionGroupCpt {
	public const TYPE = 'alovio_option_group';

	public function register(): void {
		add_action( 'init', array( $this, 'register_type' ) );
	}

	public function register_type(): void {
		// Deliberate deviation from spec §3.1's "capabilities manage_woocommerce":
		// the CPT is invisible (no UI, no REST) and is only reached through the
		// clpo/v1 REST layer, where every route checks manage_woocommerce.
		register_post_type( self::TYPE, array(
			'public'          => false,
			'show_ui'         => false,
			'show_in_rest'    => false,
			'supports'        => array( 'title' ),
			'capability_type' => 'post',
			'map_meta_cap'    => true,
		) );
	}
}
```

`includes/Groups/GroupRepository.php` — CRUD + pure normalizers. Public API (all `manage_woocommerce`-gated at the REST layer, not here):

```php
final class GroupRepository {
	public const META_FIELDS     = '_clpo_fields';
	public const META_ASSIGNMENT = '_clpo_assignment';
	public const META_PRIORITY   = '_clpo_priority';

	/** @return array{mode:string, ids:int[]} */
	public static function normalize_assignment( $raw ): array; // modes: all|categories|products; ids: unique positive ints, [] for 'all'
	/** Canonical array shape used by REST/resolver/renderer. */
	public static function to_array( int $id, string $title, string $status, array $group, array $assignment, int $priority ): array;

	public function get( int $id ): ?array;                  // null if not TYPE
	public function save( int $id, array $data ): array;     // 0 = create; normalizes fields via FieldSchema::normalize + assignment; returns to_array
	public function delete( int $id ): bool;
	public function duplicate( int $id ): ?array;            // " (copy)" title, status draft
	/** @return array[] all groups (any status) as to_array shapes, priority+title ordered */
	public function all(): array;
	/** @return array[] published groups only */
	public function published(): array;
}
```

Implementation notes: store fields/assignment as `wp_slash( wp_json_encode( … ) )` meta (same pattern as `FieldRepository::save`); `all()` uses `get_posts( [ 'post_type' => self::TYPE, 'post_status' => ['publish','draft'], 'numberposts' => -1, 'orderby' => ['meta_value_num' => 'ASC', 'title' => 'ASC'], 'meta_key' => self::META_PRIORITY ] )` — set the priority meta on every save so the orderby is total.

Wire `( new Groups\OptionGroupCpt() )->register();` in `Plugin::boot`.

- [ ] **Step 4: Run tests** — `composer test` → PASS.

- [ ] **Step 5: Commit**

```bash
git add includes/Groups/OptionGroupCpt.php includes/Groups/GroupRepository.php includes/Plugin.php tests/php/Unit/GroupRepositoryTest.php
git -c user.name='Tahir' -c user.email='ttaxiir@gmail.com' commit -m "feat: option-group CPT + repository (spec §3.1)"
```

### Task 1.3: GroupResolver

**Files:**
- Create: `includes/Groups/GroupResolver.php`
- Test: `tests/php/Unit/GroupResolverTest.php`

- [ ] **Step 1: Failing tests for the pure matcher**

```php
final class GroupResolverTest extends TestCase {
	private function groups(): array {
		return array(
			array( 'id' => 1, 'title' => 'B', 'status' => 'publish', 'fields' => array(), 'assignment' => array( 'mode' => 'all', 'ids' => array() ), 'priority' => 10 ),
			array( 'id' => 2, 'title' => 'A', 'status' => 'publish', 'fields' => array(), 'assignment' => array( 'mode' => 'categories', 'ids' => array( 5 ) ), 'priority' => 10 ),
			array( 'id' => 3, 'title' => 'C', 'status' => 'publish', 'fields' => array(), 'assignment' => array( 'mode' => 'products', 'ids' => array( 77 ) ), 'priority' => 1 ),
			array( 'id' => 4, 'title' => 'D', 'status' => 'draft',   'fields' => array(), 'assignment' => array( 'mode' => 'all', 'ids' => array() ), 'priority' => 1 ),
		);
	}
	public function test_all_mode_matches_everything(): void {
		$r = GroupResolver::filter_groups( $this->groups(), 999, array() );
		$this->assertSame( array( 1 ), array_column( $r, 'id' ) ); // only group 1 (draft excluded, cat 5 absent, product≠77)
	}
	public function test_category_match_includes_ancestor_ids_passed_in(): void {
		$r = GroupResolver::filter_groups( $this->groups(), 999, array( 5, 9 ) );
		$this->assertSame( array( 2, 1 ), array_column( $r, 'id' ) ); // priority tie → title A before B
	}
	public function test_product_match_and_priority_order(): void {
		$r = GroupResolver::filter_groups( $this->groups(), 77, array() );
		$this->assertSame( array( 3, 1 ), array_column( $r, 'id' ) ); // priority 1 before 10
	}
	public function test_draft_never_matches(): void {
		$r = GroupResolver::filter_groups( $this->groups(), 77, array( 5 ) );
		$this->assertNotContains( 4, array_column( $r, 'id' ) );
	}
}
```

- [ ] **Step 2: Run** → FAIL (class missing).

- [ ] **Step 3: Implement**

```php
final class GroupResolver {
	/** Pure: filter+sort canonical group arrays for a product. */
	public static function filter_groups( array $groups, int $product_id, array $category_ids ): array {
		$hit = array_values( array_filter( $groups, static function ( $g ) use ( $product_id, $category_ids ) {
			if ( 'publish' !== ( $g['status'] ?? '' ) ) { return false; }
			$a = $g['assignment'];
			if ( 'all' === $a['mode'] ) { return true; }
			if ( 'products' === $a['mode'] ) { return in_array( $product_id, $a['ids'], true ); }
			return (bool) array_intersect( $a['ids'], $category_ids );
		} ) );
		usort( $hit, static fn( $x, $y ) => ( $x['priority'] <=> $y['priority'] ) ?: strcasecmp( $x['title'], $y['title'] ) );
		return $hit;
	}

	/** WP glue: per-request static cache (spec §3.2). Category ids include ancestors. */
	public static function for_product( int $product_id ): array {
		static $cache = array();
		if ( ! isset( $cache[ $product_id ] ) ) {
			$cats = array();
			foreach ( (array) wc_get_product_term_ids( $product_id, 'product_cat' ) as $cid ) {
				$cats[] = (int) $cid;
				$cats   = array_merge( $cats, array_map( 'intval', get_ancestors( (int) $cid, 'product_cat' ) ) );
			}
			$cache[ $product_id ] = self::filter_groups( ( new GroupRepository() )->all(), $product_id, array_unique( $cats ) );
		}
		return $cache[ $product_id ];
	}
}
```

(`filter_groups` re-checks `status`, so passing `all()` is safe and lets the hub reuse the same call.)

- [ ] **Step 4: Run** — `composer test` → PASS. (Arrow functions are PHP 7.4 syntax — the snippet is compatible with the repo minimum as-is; sanity-check with `php -l`.)

- [ ] **Step 5: Commit** — stage the two files + test; message `"feat: GroupResolver — assignment matching + per-request cache (spec §3.2)"`.

### Task 1.4: Renderer + validation read resolved groups (multi-group)

**Files:**
- Modify: `includes/Frontend/ProductFormRenderer.php` (render every resolved group as its own `.apo-options` block), `includes/Cart/CartIntegration.php::validate` (iterate groups)
- Test: extend `tests/php/Unit/` only where logic is pure (renderer stays live-QA'd; validation loop gets a unit test)

- [ ] **Step 1: Failing test — validate() iterates all resolved groups**

Extract a pure helper first so it is testable: `CartItemShape::collect_errors( array $groups, array $posted ): array` — runs `OptionSanitizer::sanitize` + `Validator::validate` per group and merges error arrays. Test: two groups, each with one required text field (`g1_note`, `g2_note`), posted values only for `g1_note` → exactly 1 error mentioning the g2 label.

- [ ] **Step 2: Run** → FAIL (method missing).

- [ ] **Step 3: Implement**

- `ProductFormRenderer::render()`: replace the single `$this->repo->get( $product_id )` with `GroupResolver::for_product( $product_id )` and loop: each group renders the existing block (fields, rules `<script>`, total line) — wrap each in `data-apo-group="{id}"`. Skip groups with empty fields. Delete the `FieldRepository` constructor dependency here.
- `CartIntegration::validate()`: call the new `collect_errors( GroupResolver::for_product( $pid ), $this->posted() )`.
- `src/frontend.js` — conditional-logic wiring already iterates every `script.apo-rules` node, but the TOTALS wiring is form-scoped and breaks with 2+ priced groups: `frontend.js:25` resolves `form.querySelector( '.apo-options-total__value' )`, i.e. every group writes its subtotal into the FIRST group's element. Fix (one line): `const totalEl = optionsEl && optionsEl.querySelector( '.apo-options-total__value' );` (scope to `node.closest('.apo-options')`, which is already computed as `optionsEl`).
- **Known limitation (document in code comment, spec-level):** two groups on one product may both define a field id like `note`; the frozen `apo[<field_id>]` POST format (spec §4) collides. Acceptable for 2.0 (builder auto-ids are unique per group creation flow); a duplicate-id warning across resolved groups is a post-2.0 hub nicety.

- [ ] **Step 4: Run** — `composer test` → PASS; `npm run test:js` → PASS.

- [ ] **Step 5: Commit** — `"feat: storefront + validation render/check all resolved groups (spec §3.2)"`.

### Task 1.5: Cart list-shape + legacy session shim + addon_total

**Files:**
- Create: `includes/Cart/CartItemShape.php` — the three PURE statics (`normalize_apo`, `pick_group`, `collect_errors`) live here so `CartIntegration` stays hooks-only (it grows again in Chunk 3 with file-token wiring)
- Modify: `includes/Cart/CartIntegration.php` (`add_cart_item_data`, `get_from_session`, `recalculate`, `display_in_cart`, `add_order_item_meta` — all delegate shape logic to `CartItemShape`)
- Test: `tests/php/Unit/CartItemShapeTest.php`

- [ ] **Step 1: Failing tests for the pure normalizer + recalc rule (spec §3.5.4, §4)**

```php
final class CartItemShapeTest extends TestCase {
	public function test_legacy_single_map_becomes_group0_list(): void {
		$legacy = array( 'options' => array( 'a' => 'x' ), 'base_price' => 10.0, 'unique_key' => 'k' );
		$out = CartItemShape::normalize_apo( $legacy );
		$this->assertSame( 0, $out[0]['group_id'] );
		$this->assertSame( 10.0, $out[0]['base_price'] );
		$this->assertSame( 0.0, $out[0]['addon_total'] ); // missing → default 0 (reviewer note)
	}
	public function test_new_shape_passes_through(): void {
		$list = array( array( 'group_id' => 5, 'options' => array(), 'base_price' => 1.0, 'addon_total' => 2.5, 'unique_key' => 'k' ) );
		$this->assertSame( $list, CartItemShape::normalize_apo( $list ) );
	}
	public function test_pick_group_for_entry_prefers_id_then_any_for_legacy(): void {
		$groups = array( array( 'id' => 5, /* … */ ), array( 'id' => 9, /* … */ ) );
		$this->assertSame( 5, CartItemShape::pick_group( $groups, 5 )['id'] );
		$this->assertSame( 5, CartItemShape::pick_group( $groups, 0 )['id'] ); // legacy: first resolved
		$this->assertNull( CartItemShape::pick_group( $groups, 77 ) );          // deleted mid-cart
	}
}
```

- [ ] **Step 2: Run** → FAIL.

- [ ] **Step 3: Implement**

- `normalize_apo( $apo ): array` — static, pure: legacy map (detect: has `options` key and no integer-indexed list) → `array( array( 'group_id' => 0, …, 'addon_total' => (float) ( $apo['addon_total'] ?? 0 ) ) )`; already-a-list → cast/fill each entry's keys.
- `pick_group( array $groups, int $group_id ): ?array` — static, pure: exact id match; `0` → first resolved group; else null.
- `add_cart_item_data`: loop `GroupResolver::for_product( $product_id )`, build one entry per non-empty group `{group_id, options, base_price, addon_total, unique_key}` (`addon_total` = `PriceCalculator::addon_total(...)` at add time; `unique_key` = md5 of group_id+options). `$cart_item_data['apo']` = the list; skip when no groups.
- `get_from_session`: `$cart_item['apo'] = self::normalize_apo( $values['apo'] )`.
- `recalculate`: price = `base_price` (from FIRST entry — all entries share the purchased entity) + Σ per entry: resolved group → recompute via `PriceCalculator`; unresolved → stored `addon_total` (spec §3.5.4). Update each entry's `addon_total` after recompute.
- `display_in_cart` / `add_order_item_meta`: iterate entries; resolve labels via `pick_group` fields, falling back to raw field ids (current behavior) when unresolved.

- [ ] **Step 4: Run** — `composer test` → PASS (all suites, incl. untouched money tests).

- [ ] **Step 5: Commit** — `"feat: cart carries per-group entries + legacy session shim + addon_total fallback (spec §3.5.4, §4)"`.

### Task 1.6: Migration routine + uninstall

**Files:**
- Create: `includes/Setup/Migration.php`
- Modify: `includes/Plugin.php` (wire on `admin_init`), `uninstall.php` (NOTE: `CLPO_VERSION`/header stay `1.0.0` until release — Task 5.4 bumps them; migration compares against the literal `'2.0.0'`)
- Test: `tests/php/Unit/MigrationTest.php`

- [ ] **Step 1: Failing tests for the pure planner**

`Migration::plan( array $rows ): array` — input rows `{product_id, product_title, fields_json}` (the WP glue queries postmeta); output per row: `{title: "{product_title} — Options", fields: <decoded+normalized>, assignment: {mode:products, ids:[product_id]}, priority: 10}`; rows with empty/invalid JSON are skipped. Tests: happy path title/assignment shape; invalid JSON skipped; already-migrated rows (glue filters those, planner unaware).

- [ ] **Step 2: Run** → FAIL.

- [ ] **Step 3: Implement**

```php
final class Migration {
	public const OPTION = 'clpo_version';

	public function register(): void {
		add_action( 'admin_init', array( $this, 'maybe_run' ) );
	}
	public function maybe_run(): void {
		if ( version_compare( (string) get_option( self::OPTION, '1.0.0' ), '2.0.0', '>=' ) ) { return; }
		$remaining = $this->run(); // one batch (≤200 rows) per admin load
		if ( 0 === $remaining ) {
			update_option( self::OPTION, '2.0.0' ); // literal — header/CLPO_VERSION bump happens at release (Task 5.4)
		}
	}
	public function run(): int {
		// WP glue: query postmeta for _clpo_field_group where product lacks _clpo_migrated_to
		// (LIMIT 200), call self::plan(), create a CPT group per row via GroupRepository::save(0, …)
		// with status publish, then update_post_meta( product, '_clpo_migrated_to', $group_id ).
		// Original _clpo_field_group meta is NOT touched (spec §3.5.2).
		// Returns the count of rows STILL unmigrated (re-query after the batch), so maybe_run
		// only marks completion when none remain — the next admin load resumes otherwise.
	}
	public static function plan( array $rows ): array { /* pure, tested */ }
}
```

Query: `$wpdb->get_results` join `postmeta` (`_clpo_field_group`) × `posts` (title) with `NOT EXISTS` on `_clpo_migrated_to` — cap batches at 200 per run to avoid timeouts on huge catalogs; `maybe_run` re-enters on the next admin load until none remain, THEN sets the option.

Uninstall: append to the existing opt-in block — delete all `alovio_option_group` posts (`get_posts` + `wp_delete_post( id, true )`), `delete_post_meta_by_key( '_clpo_migrated_to' )`, `delete_option( 'clpo_version' )`.

- [ ] **Step 4: Run** — `composer test` → PASS.

- [ ] **Step 5: Live smoke (wp-env)**

```bash
npm run env:start
npx wp-env run cli wp option delete clpo_version
# seed one product (id 15 in the default wp-env fixture — adjust) with 1.x meta:
npx wp-env run cli wp post meta update 15 _clpo_field_group \
  '{"fields":[{"id":"note","type":"text","label":"Note","required":true,"price":5}]}'
# migration fires on admin_init → load any wp-admin page IN THE BROWSER (wp-cli alone won't trigger it),
# or force it: npx wp-env run cli wp eval 'do_action("admin_init");'
npx wp-env run cli wp post meta get 15 _clpo_migrated_to   # → group id
npx wp-env run cli wp post list --post_type=alovio_option_group --format=table
```

Expected: one publish group titled "<Product> — Options"; storefront product page still shows its fields (now via resolver).

- [ ] **Step 6: Commit** — stage Migration.php, Plugin.php, uninstall.php, main file, test; `"feat: 1.x→2.0 migration (product meta → CPT groups, idempotent, batched) + uninstall coverage (spec §3.5)"`.

**Chunk 1 exit criteria:** all tests green; a 1.0-seeded wp-env site upgrades in place: product page renders identical fields through the resolver, cart prices unchanged, legacy cart session survives (manual: add-to-cart before the code switch, reload after).

---

## Chunk 2: REST API, hub SPA, builder port, metabox summary (spec §3.3–3.4, §5)

End state: the old builder metabox is gone; groups are managed in **WooCommerce → Product Options** (list + ported CF builder + settings); the product editor shows a slim summary box with deep links.

**Porting ground rules (apply to every task in this chunk):**
- Copy source: `~/woo-checkout-fields/src/builder/*`, `~/woo-checkout-fields/assets/css/builder.css`, jest suites `~/woo-checkout-fields/tests/js/{store,templates,simulation}.test.js` as pattern references.
- Rename map (verbatim, apply with grep-verify after every copy): `clcf`→`clpo`, `CLCF`→`CLPO`, `corelabs-checkout-fields`→`corelabs-product-options`, store key `clcf/builder`→`clpo/builder`, `Checkout Fields`→`Product Options` (display strings).
- Verification after each copy: `rg -n "clcf|CLCF|checkout-fields|Checkout Fields" src/ assets/css/builder.css` → 0 hits; `npm run build` → compiles.
- AppShell's "Try Alovio Calculator" cross-promo link: KEEP (same family cross-promo applies here) — just verify the URL is still `https://alovio.org/calculator`.

### Task 2.1: Groups REST controller

**Files:**
- Create: `includes/Admin/GroupsRestController.php`
- Modify: `includes/Plugin.php` (wire), `includes/Admin/RestController.php` (drop POST route — GET only)
- Test: `tests/php/Unit/GroupsRestShapeTest.php`

- [ ] **Step 1: Failing tests for pure response shaping**

`GroupsRestController::summarize( array $group ): array` → `{id, title, status, field_count, priced_count, assignment_summary, priority}`; `assignment_summary` examples: `"All products"`, `"3 categories"`, `"1 product"`. `priced_count` counts fields with `price > 0` or type `price`. Write 3 assertions (all-mode, categories, priced counting).

- [ ] **Step 2: Run** → FAIL. **Step 3: Implement**

Routes (namespace `clpo/v1`, all `permission_callback` = `current_user_can('manage_woocommerce')`; nonce via standard REST cookie auth):

| Route | Handler behavior |
|---|---|
| `GET /groups` | `array_map( summarize, repo->all() )` |
| `POST /groups` | `repo->save( 0, body )` (defaults: title "Untitled group", status draft) → full `to_array` |
| `GET/PUT/DELETE /groups/{id}` | get→404 if null; PUT validates body via repo->save; DELETE→`repo->delete` |
| `POST /groups/{id}/duplicate` | `repo->duplicate` → 404 if source missing |
| `GET /products/search?q=` | `wc_get_products( [ 's'=>q, 'limit'=>20, 'return'=>'objects' ] )` → `{id, name}` pairs |
| `GET /categories/search?q=` | `get_terms( [ 'taxonomy'=>'product_cat', 'name__like'=>q, 'number'=>20, 'hide_empty'=>false ] )` → `{id, name, path}` (path = ancestors joined with " › ") |
| `GET/POST /settings` | get/update `clpo_remove_data_on_uninstall` (bool). *(Not in the spec's route table; required by spec §3.3's settings screen — the minimal persistence for it.)* |

Spec §3.4's `/export` · `/import` routes are deliberately NOT built here — they land with templates in Chunk 5 (Task 5.1).

In `RestController` delete the POST array from `register_rest_route` (keep GET + `can_edit`).

- [ ] **Step 4: Run** — `composer test` → PASS. **Step 5: Commit** — `"feat: clpo/v1 groups CRUD + pickers + settings REST (spec §3.4)"`.

### Task 2.2: Hub admin page + asset wiring

**Files:**
- Create: `includes/Admin/HubPage.php` (copy pattern: `~/woo-checkout-fields/includes/Admin/SettingsPage.php` — submenu under `woocommerce`, slug `alovio-product-options`, mount `<div id="clpo-hub-root"></div>`, `admin_body_class` += `clpo-hub-page`)
- Modify: `webpack.config.js` (entries: drop `index`, add `hub: src/hub/index.js`; keep `frontend`), `includes/Admin/BuilderAssets.php` (enqueue `build/hub.js|css` ONLY on `woocommerce_page_alovio-product-options` hook; localize `CLPO_HUB` = `{root, nonce, fieldTypes, operators, templatesUrl?}`; drop the old post.php/metabox enqueue + `add_meta_box`), `includes/Plugin.php`
- Create: `src/hub/index.js` (minimal mount: renders "Hub OK" placeholder until Task 2.4)

- [ ] **Step 1: Implement the page + entry (no test framework for menu registration — verify live)**
- [ ] **Step 2: Build + live check**

```bash
npm run build && npm run env:start
# wp-admin → WooCommerce → Product Options → "Hub OK" renders, no console errors
```

- [ ] **Step 3: Run suites** — nothing is deleted in this task, so both suites pass unmodified (Jest doesn't read webpack entries). `composer test` + `npm run test:js` → PASS.
- [ ] **Step 4: Commit** — `"feat: Product Options hub page + hub bundle scaffold (spec §3.3)"`.

### Task 2.3: Delete the old builder metabox UI sources

**Files:**
- Delete: `src/builder/*` (old 9-file builder), `src/index.js`, `tests/js/store.test.js` (Jest resolves its top-of-file `../../src/builder/reducer` import even under `describe.skip`, so the suite MUST be deleted with the code it tests; its expectations are re-ported as `builder-store.test.js` in Task 2.4), `assets/css/builder.css` (replaced by the CF port in 2.4)

- [ ] **Step 1:** `git rm -r src/builder src/index.js assets/css/builder.css tests/js/store.test.js`
- [ ] **Step 2:** `npm run build` → compiles (hub entry only); `npm run test:js` → remaining suites (conditional-logic, price-update) PASS.
- [ ] **Step 3: Commit** — `"chore: remove v1 metabox builder sources + their store suite (replaced by CF port)"`.

### Task 2.4: Port the CF builder wholesale

**Files:**
- Create (copied+renamed): `src/builder/{AppShell,Palette,PreviewCanvas,FieldPreview,SettingsPanel,SimulationBar}.jsx`, `src/builder/panels/{General,Logic}.jsx`, `src/builder/{store,reducer,describe,simulation}.js`, `assets/css/builder.css`
- Create: `tests/js/builder-store.test.js` (port of CF `store.test.js`)
- NOT copied: CF `panels/Fee.jsx` (→ Pricing, Chunk 3), CF `panels/Layout.jsx` (checkout-specific), CF `templates.js` (→ Chunk 5), CF `data-sources.js`/`simulation.js` cart presets (see adaptation below)

- [ ] **Step 1: Copy + rename**

```bash
mkdir -p src/builder/panels
cp ~/woo-checkout-fields/src/builder/{AppShell.jsx,Palette.jsx,PreviewCanvas.jsx,FieldPreview.jsx,SettingsPanel.jsx,SimulationBar.jsx,store.js,reducer.js,describe.js,simulation.js} src/builder/
cp ~/woo-checkout-fields/src/builder/panels/{General.jsx,Logic.jsx} src/builder/panels/
cp ~/woo-checkout-fields/assets/css/builder.css assets/css/builder.css
# apply the rename map, then:
rg -n "clcf|CLCF|checkout-fields|Checkout Fields" src/builder assets/css/builder.css   # → 0 hits
```

- [ ] **Step 2: Adapt the checkout-isms (each is a small, local edit)**
  - `AppShell.jsx`: props become `{ groupId, onBack }`; load/save via `clpo/v1/groups/{id}` (GET on mount / PUT on save — body `{title, status, fields, assignment, priority}` from the store); header breadcrumb "Product Options › {title}" + a `← Groups` back button calling `onBack`; keep status pill/undo/⌘S verbatim. "Save & publish" sets `status: 'publish'`; a secondary header select toggles Active/Draft.
  - `store.js`/`reducer.js`: store key `clpo/builder`; state gains `title`, `status`, `assignment`, `priority` alongside `fields` (+ actions/selectors + undo history covering fields only, as in CF).
  - `panels/Logic.jsx`: delete the `@context` source picker — condition sources = sibling fields only (options from the store's other fields, as v1's ConditionEditor did); operator list reads `window.CLPO_HUB.operators` (2.2 localizes it — the copied file's `CLPO_BUILDER` global after rename is never set). `describe.js`: keep, drop context-token branches.
  - `SimulationBar.jsx` + `simulation.js`: replace cart presets with field-value simulation — the bar shows chips of currently-set preview values (from interacting with the canvas inputs), an **Options total** readout (compute with `computeAddonTotal` from `src/frontend/price-update.js` — import it), and a Reset button. State lives in the store (`sim` map keyed by field id).
  - `PreviewCanvas.jsx`: CF imports `buildValues` from `../checkout-conditions/data-sources` (NOT copied) and `simToCtx`/`previewFieldValues` from `./simulation` (rewritten above). Replace the values side: feed `activeMap` with the store's `sim` value map merged over field defaults — the existing `import { activeMap } from '../frontend/conditional-logic'` resolves as-is in this repo (`src/frontend/conditional-logic.js` exports it).
  - `FieldPreview.jsx`: map the 10 current types (text, textarea, number, checkbox, radio, select, price, heading, swatch, date) onto CF's renderers. Two renderers CF lacks — port both from v1's deleted `LivePreview.jsx` (recover with `git show $(git rev-list -1 HEAD -- src/builder/LivePreview.jsx):src/builder/LivePreview.jsx`): the swatch dot markup AND the `price` (surcharge) renderer (`case 'price'` → `.apo-fee`).
  - `Palette.jsx`: palette source = `window.CLPO_HUB.fieldTypes`; CF's Palette also imports `{ TEMPLATES } from './templates'` and renders a "Start from a template" chips section — `templates.js` is NOT copied, so strip the import + chips section (templates return as the hub `#/templates` screen in Chunk 5).

- [ ] **Step 3: Port the store jest suite** — copy CF `tests/js/store.test.js` → `tests/js/builder-store.test.js`, rename keys, extend for `assignment/priority` actions. Remove the Task 2.2 `describe.skip`. Run: `npm run test:js` → PASS.
- [ ] **Step 4:** `npm run build` → compiles. **Step 5: Commit** — `"feat: port Alovio CF builder (shell/palette/canvas/settings/sim) to clpo (spec §5)"`.

### Task 2.5: Hub router + Groups list screen

**Files:**
- Create: `src/hub/router.js` (tiny hash router: parse `#/groups`, `#/groups/{id}`, `#/templates`, `#/settings`; default `#/groups`), `src/hub/screens/GroupsList.jsx`, `src/hub/screens/Settings.jsx`
- Modify: `src/hub/index.js` (route → screen; `#/groups/{id}` renders `<AppShell groupId onBack>`)
- Test: `tests/js/hub-router.test.js`

- [ ] **Step 1: Failing router test** — `parseRoute('#/groups/12')` → `{name:'builder', id:12}`; `''`→`{name:'list'}`; `'#/settings'`→`{name:'settings'}`; unknown→`{name:'list'}`.
- [ ] **Step 2: Run** → FAIL. **Step 3: Implement router + screens**
  - `GroupsList.jsx`: fetch `GET /groups`; table (Name → `#/groups/{id}`, status pill, fields, priced, assignment summary); row actions Duplicate (POST …/duplicate → refresh) and Delete (`window.confirm` → DELETE); header bar (reuse `.clpo-hdr` classes): title + "＋ New group" (POST /groups → navigate to builder); empty state with the same CTA.
  - `Settings.jsx`: checkbox bound to `GET/POST /settings`.
  - `#/templates` renders a "coming in this release" placeholder until Chunk 5 Task 5.2 replaces it.
- [ ] **Step 4:** `npm run test:js` → PASS; `npm run build`.
- [ ] **Step 5: Live QA (wp-env):** create → builder opens → rename title → add a text field → Save & publish → back → list shows 1 group Active with 1 field; duplicate + delete work; refresh deep link `#/groups/{id}` restores the builder.
- [ ] **Step 6: Commit** — `"feat: hub SPA — router, groups list, settings screens (spec §3.3)"`.

### Task 2.6: Assignment panel

**Files:**
- Create: `src/builder/panels/Assignment.jsx`
- Modify: `src/builder/SettingsPanel.jsx` — CF renders the tab strip only when a field is selected and shows a "Select a field…" empty state otherwise; REPLACE that empty state wholesale with the Assignment panel (group-level settings when nothing is selected; field tabs when a field is selected). Also `src/builder/reducer.js` (assignment/priority actions from 2.4 get UI)
- Test: `tests/js/assignment-reducer.test.js`

- [ ] **Step 1: Failing reducer tests** — `setAssignment({mode:'categories', ids:[5]})` updates state; `setPriority(5)` clamps to int ≥ 0; switching mode to `all` clears ids.
- [ ] **Step 2: Run** → FAIL. **Step 3: Implement**

Panel UI: radio (All products / Categories / Specific products); for the last two an async search-select (`@wordpress/components` `ComboboxControl`) hitting `/categories/search` / `/products/search`, selected items as removable chips; NumberControl for priority with help text "Lower renders first when several groups apply." Persisted through the same PUT (already in the save body since 2.4).

- [ ] **Step 4:** `npm run test:js` → PASS; build. **Step 5: Live QA:** assign a group to 1 product → storefront shows it on that product only; category mode → shows on all products of the category (incl. a child category — ancestors per resolver). **Step 6: Commit** — `"feat: Assignment panel — all/categories/products + priority (spec §5)"`.

### Task 2.7: Product metabox summary + create-for-product

**Files:**
- Create: `includes/Admin/ProductSummaryBox.php`
- Modify: `includes/Plugin.php` (wire; ensure the old builder metabox registration is fully gone)
- Test: `tests/php/Unit/ProductSummaryBoxTest.php` (pure renderer)

- [ ] **Step 1: Failing test** — `ProductSummaryBox::items( array $groups ): array` maps resolved groups → `{id, title, field_count, edit_url}` with `edit_url = admin_url('admin.php?page=alovio-product-options#/groups/{id}')`; empty input → empty array.
- [ ] **Step 2: Run** → FAIL. **Step 3: Implement**

Server-rendered metabox (side context, `manage_woocommerce` check in render): list from `GroupResolver::for_product`, each row "»{title}« — N fields · Edit ↗"; footer button **Create options for this product** = link to `admin-post.php?action=clpo_create_product_group&product={id}&_wpnonce=…`; the `admin_post_clpo_create_product_group` handler creates a draft group titled "{product} — Options" pre-assigned `{mode:products, ids:[id]}` and redirects to its builder URL. *(Deliberate simplification vs spec §3.4 wording: the metabox renders server-side via the resolver — the kept GET `/product/{id}/fields` route remains for back-compat consumers only.)*

- [ ] **Step 4:** `composer test` → PASS. **Step 5: Live QA:** product edit → box lists groups; create button lands in the builder with assignment pre-set; Edit ↗ deep-links. **Step 6: Commit** — `"feat: slim product metabox — resolved-group summary + create-for-product (spec §3.3)"`.

**Chunk 2 exit criteria:** hub fully manages groups end-to-end (create/edit/assign/publish/duplicate/delete); old metabox builder gone; product metabox summary + deep links live-verified; all suites green; `rg "clcf|isPro" src includes` → 0 hits.

---

## Chunk 3: Field types (18) + pricing modes (5) + formula engine (spec §6, §7)

End state: all 18 types work end-to-end (builder preview → storefront render → sanitize → validate → price → cart/order display); 5 pricing modes incl. `per_char` and `formula`; PHP↔JS formula parity fixture green.

**Per-type definition of done (checklist template — every type task below repeats it):**
1. `FieldTypes::TYPES` entry; 2. `FieldSchema` normalization (type-specific keys); 3. `OptionSanitizer` case; 4. `Validator` case; 5. `ProductFormRenderer::render_input` case; 6. builder `FieldPreview` renderer + `panels/General`/`panels/Options` settings where needed; 7. unit tests for 3–5; 8. cart/order display formatting where the raw value isn't presentable.

### Task 3.1: Simple input types — email, phone, url, time

**Files:**
- Modify: `includes/Fields/FieldTypes.php`, `includes/Fields/FieldSchema.php`, `includes/Cart/OptionSanitizer.php`, `includes/Cart/Validator.php`, `includes/Frontend/ProductFormRenderer.php`, `src/builder/FieldPreview.jsx`
- Test: extend `tests/php/Unit/{OptionSanitizerTest,ValidatorTest}.php`
- Reference: `~/woo-checkout-fields/includes/Checkout/{Sanitizer,Validator}.php` — **email/url logic ports from CF** (sanitize_email; esc_url_raw + scheme check); **time validation ports from CF's Validator** (`^([01]\d|2[0-3]):[0-5]\d$` — validator-level, sanitizer just trims); **phone is NEW, stricter than CF** (CF has no phone case — it falls into generic text): sanitizer keeps only `+ - ( ) space digits`, validator requires ≥5 digits when non-empty.

- [ ] **Step 1: Failing tests** — per type: sanitizer keeps valid value, strips invalid (email → '' on garbage; url rejects `javascript:`; phone strips letters, keeps `+ - ( ) space digits`); validator errors on invalid non-empty value with the field label in the message (time: `^([01]\d|2[0-3]):[0-5]\d$`; phone: ≥5 digits).
- [ ] **Step 2: Run** → FAIL. **Step 3: Implement** (render: `<input type="email|tel|url|time">` reusing the existing text-input attr pipeline — placeholder/default/required/descby all come free).
- [ ] **Step 4: Run** — `composer test` → PASS; `npm run build`. **Step 5: Commit** — `"feat: email/phone/url/time field types (ported from CF, spec §6)"`.

### Task 3.2: Quantity + button group + image swatch

**Files:** same set as 3.1 + `src/builder/panels/Options.jsx` (NEW — options editor: shared by radio/select/swatch/buttons/image-swatch; extracted from what General.jsx hosts for choice fields today) + `src/frontend/conditional-logic.js` (`readValues`) + `src/frontend/price-update.js` (engaged/per_unit gates) + `assets/css/{builder,frontend}.css`
- Test: extend sanitizer/validator tests + `tests/js/{conditional-logic,price-update}.test.js` (plain reducer/function tests — the repo has no `@testing-library`; don't add it)

- [ ] **Step 1: Failing tests (PHP)**
  - quantity: sanitizer casts to int, clamps to min/max/step (schema keys reuse the number field's `min/max/step`); validator errors below min / above max; value 0 with `per_unit` price → fee 0 (PriceCalculator regression guard already exists for number — extend to quantity).
  - buttons: sanitizer accepts only listed option labels (same allowlist rule as radio).
  - image swatch: options are `{label, image}` maps; sanitizer matches on label; renderer prints `<img>` with `esc_url`.
- [ ] **Step 2: Failing tests (JS mirrors — spec §6 "readValues support where input shape differs")**
  - `readValues`: buttons/image-swatch (and the pre-existing latent `swatch` gap) render as RADIO inputs, but `readValues` special-cases only `f.type === 'radio'` — the generic branch reads the FIRST radio's value ignoring `:checked`. Test: form with a checked 2nd option → value = 2nd option. Fix keyed on INPUT SHAPE, not type: if the queried input is `type=radio`, re-query with `:checked`.
  - `price-update.js`: `computeAddonTotal` treats `per_unit` and the numeric engaged-check as `f.type === 'number'` only — extend both gates to `quantity` (and add: quantity value 0 → not engaged). Test: quantity 3 × $2 per_unit → 6; quantity 0 → 0.
- [ ] **Step 3: Run** → FAIL (PHP + JS). **Step 4: Implement**
  - Storefront quantity = number input + −/+ stepper buttons (`.apo-qty`), buttons group = radio inputs styled as pills (`.apo-btnopt`, keyboard/AT = plain radios), image swatch = the colour-swatch markup with a 40×40 `<img>` instead of the dot.
  - JS: the `readValues` shape fix + `price-update` quantity gates per the tests above.
  - Builder: `panels/Options.jsx` rows gain per-option **image** (MediaUpload from `@wordpress/media-utils` — NEW devDependency; the hub page also needs `wp_enqueue_media()` in `BuilderAssets`, add it there; fallback URL input) when type = image swatch; palette entries for the three types.
- [ ] **Step 5: Run** — both suites PASS; build. **Step 6: Commit** — `"feat: quantity, button group, image swatch field types + radio-shape readValues fix (spec §6)"`.

### Task 3.3: File upload — port + cart lifecycle

**Files:**
- Create: `includes/Cart/FileUploads.php` (port of `~/woo-checkout-fields/includes/Checkout/FileUploads.php`, 229 lines: REST upload route, token store, obfuscated upload dir, allowlist/max-size, orphan cron, `validate_tokens`, `consume`)
- Create: `src/frontend/uploader.js` (port CF's front-end uploader glue; renders into `.apo-file` wrappers)
- Modify: sanitizer/validator/renderer/FieldPreview/`includes/Frontend/FrontendAssets.php` (localize upload route+nonce+limits), `includes/Cart/CartIntegration.php` (mark/refresh/clear carted tokens; attach on order), `includes/Plugin.php`
- Test: `tests/php/Unit/FileUploadsCartTest.php`

- [ ] **Step 1: Port the class + route** (rename `clcf`→`clpo`; route `clpo/v1/upload`). Run `composer test` → still PASS (no behavior asserted yet).
- [ ] **Step 2: Failing tests for the CART adaptations (spec §6)** — pure token-state helpers:

```php
// FileUploads::is_expired( array $tokenMeta, int $now ): bool
// - not carted: expires after 48h (CF behavior)
// - carted (has 'carted_at'): expires after 30 days
// FileUploads::mark_carted / clear_carted mutate the token meta array (pure) — glue persists.
public function test_carted_token_survives_48h_window(): void { /* carted_at set, age 3 days → not expired */ }
public function test_uncarted_token_expires_after_48h(): void { /* age 3 days → expired */ }
public function test_carted_token_expires_after_30_days(): void { /* age 31 days → expired */ }
```

- [ ] **Step 3: Implement** — cron `cleanup_orphans` consults `is_expired`; `CartIntegration::add_cart_item_data` calls `mark_carted` for every file value; `get_from_session` refreshes the mark (keeps long-lived carts alive); cart-item-removed hook (`woocommerce_cart_item_removed`) clears it; `add_order_item_meta` calls `consume` (CF behavior) and stores the admin download link. Cart/checkout display (`display_in_cart` format_value) shows the token's original **filename**.
- [ ] **Step 4: Run** — `composer test` → PASS; `npm run build`. **Step 5: Live QA (wp-env):** upload on product page → cart shows filename → order meta shows download link; removing cart item frees the token (check token option/meta state via wp-cli). **Step 6: Commit** — `"feat: file upload field — CF port + carted-token lifecycle (spec §6)"`.

### Task 3.4: per_char pricing mode

**Files:**
- Modify: `includes/Cart/PriceCalculator.php`, `includes/Fields/FieldSchema.php` (mode allowlist + per-type constraint: per_char only for text/textarea), `src/frontend/price-update.js` (mirror). (Builder mode-picker UI belongs wholly to Task 3.6.)
- Test: extend `tests/php/Unit/PriceCalculatorTest.php` + `tests/js/price-update.test.js`

- [ ] **Step 1: Failing tests** — PHP+JS: `price=0.5, mode=per_char, value="Hello world"` → `5.50` (trimmed length 11 × 0.5); empty string → 0; per_char on checkbox normalizes back to fixed (schema).
- [ ] **Step 2: Run** → FAIL both. **Step 3: Implement** (PHP `mb_strlen( trim( $value ) )`; JS `value.trim().length`).
- [ ] **Step 4: Run** — both PASS. **Step 5: Commit** — `"feat: per_char pricing mode (spec §7)"`.

### Task 3.5: Formula engine port + formula pricing mode

**Files:**
- Create: `includes/Formula/{Lexer,Parser,Evaluator,DecimalMath,FormulaError,Functions}.php` (port from `~/alovio-calculator/includes/Formula/` — precedence-climbing parser over scaled ints; `Functions.php` IS ported but its `SPECS` table is stripped to `min/max/round`; remove `if()` + comparison operators from the Lexer/Parser token set; the SPECS name/arity check lives in the compile step — in the calculator that's `Formula::compile`, here it moves into `FormulaPrice::compile`), `includes/Formula/FormulaPrice.php` (NEW facade), `src/shared/formula/evaluator.js` (port of calculator's JS mirror, same strip), `tests/fixtures/formula-cases.json`
- Modify: `includes/Cart/PriceCalculator.php` (formula mode + breakdown()), `src/frontend/price-update.js` (formula mode via the JS mirror)
- Test: `tests/php/Unit/FormulaTest.php`, `tests/js/formula.test.js` (both iterate the shared fixture)

- [ ] **Step 1: Write the fixture FIRST** (`tests/fixtures/formula-cases.json`) — cases: `{expr, values, expected}` where `expected` is a number OR an error tag:
  basic arithmetic + precedence `2+3*4=14`; parens; `{a}*{b}` tokens; missing token → 0; `min(…) max(…) round(…)`; divide-by-zero → `{"error":"runtime"}`; negative result → clamped `0`; >200-char expr → `{"error":"compile"}`; malformed (`2*`, `{a`, unknown func, wrong arity) → `{"error":"compile"}`; decimal safety case `0.1+0.2=0.3` exact.
- [ ] **Step 2: Failing PHP test** — `FormulaTest` iterates the fixture calling `FormulaPrice::evaluate( string $expr, array $values ): float` (returns ≥0 float; throws nothing — BOTH error kinds return 0.0). Additionally, for `error:"compile"` cases only, assert `FormulaPrice::validate($expr)` returns an error string; for `error:"runtime"` cases (div-zero — thrown by `DecimalMath::div` during evaluation, invisible to a compile-only check) assert `validate()` returns null but `evaluate()` still yields 0.0. Run → FAIL.
- [ ] **Step 3: Port + implement the facade** — `FormulaPrice::evaluate`: length check → `Formula-style compile` (Lexer→Parser) → scale values ×100 (2dp money) via DecimalMath → `Evaluator` → unscale, clamp ≥0; catch `FormulaError` → `wc_get_logger()->warning( …, ['source'=>'alovio-product-options'] )` → 0.0. `validate()` = compile-only, returns null|error-message (for the builder inline validation + REST-side schema normalization). Run → PHP PASS.
- [ ] **Step 4: Failing JS test** — same fixture through `src/shared/formula/evaluator.js` (`evaluateFormula(expr, values)` mirrors clamp/error→0). Run → FAIL → port JS → PASS.
- [ ] **Step 5: Wire pricing** — `PriceCalculator::addon_total`: `formula` case = `FormulaPrice::evaluate( $field['formula'], $numeric_values_of_engaged_fields )`; add `PriceCalculator::breakdown( array $group, array $submitted, int $decimals, float $base ): array` returning `{field_id, label, amount}` rows (reuses the same per-field computation — refactor the loop body into a private per-field method so total() and breakdown() share it). JS `computeAddonTotal` mirrors; also export `computeBreakdown` for Chunk 4. Schema: `formula` key sanitized (length ≤200) + `FormulaPrice::validate` on save (invalid → mode falls back to fixed + REST response carries a `warnings[]` entry).
- [ ] **Step 6: Run everything** — `composer test`, `npm run test:js`, `npm run build` → ALL PASS. **Step 7: Commit** — `"feat: formula pricing — ported decimal-safe engine (PHP+JS, fixture parity) + breakdown() (spec §7)"`.

### Task 3.6: Builder Pricing panel + formula UX + palette completion

**Files:**
- Create: `src/builder/panels/Pricing.jsx` (replaces CF's Fee panel role): price amount, mode select (fixed/per-unit/percent/per-char/formula — options constrained by field type), formula textarea with `{field}` token insert buttons + inline `evaluateFormula` validation error, live example line ("2 × $5.00 = $10.00" style, computed with computeAddonTotal on current sim values)
- Modify: `src/builder/SettingsPanel.jsx` (tab strip: General / Options / Logic / Pricing), `src/builder/FieldPreview.jsx` (price badges per mode), `tests/js/pricing-panel.test.js` (reducer-level: setPrice/setPriceMode/setFormula actions)

- [ ] **Step 1: Failing reducer tests** → **Step 2:** FAIL → **Step 3: Implement panel + actions** → **Step 4:** `npm run test:js` PASS + build → **Step 5: Live QA:** build a formula field (`{width}*{height}*0.85`) in the builder, sim values, watch the total; invalid formula shows inline error and Save keeps mode=fixed warning. **Step 6: Commit** — `"feat: Pricing panel — 5 modes + formula editor with live validation (spec §5, §7)"`.

**Chunk 3 exit criteria:** 18 types in `FieldTypes::TYPES`; every type passes its sanitize/validate/render tests; formula fixture green in BOTH runtimes; live QA: a product with quantity×per_unit + formula + image swatch prices correctly into the cart (server total == storefront preview).

---

## Chunk 4: Storefront — breakdown box, variable products, polish pack (spec §9)

End state: design-B breakdown box live-updating (variations included); polish pack shipped (price suffixes, tooltips, char counter, inline validation, focus/RTL styling).

### Task 4.1: Breakdown box (server initial render + live JS)

**Files:**
- Modify: `includes/Frontend/ProductFormRenderer.php` (render `.apo-breakdown` after the LAST resolved group when any group has priced fields — replaces the per-group total line), `src/frontend.js` + `src/frontend/price-update.js` (use `computeBreakdown` across ALL groups on the page; write rows into the box), `assets/css/frontend.css`
- Test: `tests/js/breakdown.test.js`; PHP: extend renderer expectations indirectly via `PriceCalculator::breakdown` tests (already in 3.5)

- [ ] **Step 1: Failing JS test** — `renderBreakdownRows( breakdowns, base, fmt )` (pure DOM-free row-model builder in price-update.js): given base 50 and two engaged priced fields (8 and 7) returns `[ {label:'Base price', amount:50}, {label:'Engraving', amount:7}, {label:'Gift wrap', amount:8}, {label:'Total', amount:65, total:true} ]`; zero engaged priced fields → empty array (box hidden).
- [ ] **Step 2: Run** → FAIL. **Step 3: Implement**
  - PHP: box markup `<div class="apo-breakdown" data-apo-breakdown hidden><ul></ul></div>` + server renders initial rows for the no-JS default state using `PriceCalculator::breakdown` per group (base row = product price via existing base logic). `aria-live="polite"` on the container. **Remove the replaced per-group total line explicitly:** delete the `.apo-options-total` markup from `ProductFormRenderer` AND its `wirePrices` `totalEl` consumer in `frontend.js`/`price-update.js` — no dead path survives.
  - JS: one shared updater subscribed to every group's form events (frontend.js already loops groups — collect `{fields, form}` pairs, single `update()` renders rows via row-model + `formatMoney`). Box hidden when the row model is empty.
  - CSS: rows, muted labels, dashed top border on the total row — theme-inheriting fonts/colors only (spec: no hard branding).
- [ ] **Step 4: Run** — `npm run test:js` PASS; `composer test` PASS; build. **Step 5: Commit** — `"feat: storefront price breakdown box (design B, spec §9)"`.

### Task 4.2: Variable-product base tracking

**Files:**
- Modify: `src/frontend.js` (bind WooCommerce variations form events), `src/frontend/price-update.js` (mutable base), `includes/Frontend/ProductFormRenderer.php` (no change expected — verify `data-apo-base` presence only)
- Test: `tests/js/variation-base.test.js`

- [ ] **Step 1: Failing test** — a `BaseTracker` object: `track(initial)`, `set(v)`, `reset()` (→ initial), `get()`; wiring contract test: after `set(75)`, `renderBreakdownRows` uses 75 for base + percent-mode amounts recompute (percent field 10% → 7.5).
- [ ] **Step 2: Run** → FAIL. **Step 3: Implement** — in `frontend.js`: `jQuery( 'form.variations_form' ).on( 'found_variation', (e, variation) => tracker.set( parseFloat( variation.display_price ) ) ).on( 'reset_data', () => tracker.reset() )` (guarded `typeof jQuery !== 'undefined'` — Woo loads jQuery on product pages; fall back silently when absent). All price paths read the tracker.
- [ ] **Step 4: Run** — PASS + build. **Step 5: Live QA (wp-env):** variable product with a percent-mode option — select variations, watch base + percent + Total change; add to cart → cart price equals the shown Total (server parity per spec §9). **Step 6: Commit** — `"feat: breakdown box tracks selected variation price (spec §9)"`.

### Task 4.3: Polish pack — labels, tooltip, counter, inline validation

**Files:**
- Modify: `includes/Frontend/ProductFormRenderer.php` (price suffix on labels/options: append `(+{formatted})` — fixed shows amount, per_unit "+$X each", per_char "+$X / character", percent "+X%", formula shows a translatable "(price varies)"; tooltip: when description present, render a `?` button + collapsible `.apo-tip` panel instead of always-visible small text; maxlength counter markup `data-apo-counter`), `src/frontend/polish.js` (NEW: counter updates, tooltip toggle, inline validation), `src/frontend.js` (wire), `assets/css/frontend.css` (+RTL check), `includes/Cart/Validator.php` (no change — reuse messages)
- Test: `tests/js/polish.test.js`

- [ ] **Step 1: Failing JS tests** — `counterText(value, max)` → `"14 / 40"`; `validateRequiredInline(field, value)` returns the same message strings the PHP Validator produces (mirror the 3 core messages: required / must be a number / invalid selection — export a small message map fed by localized strings `CLPO_FE.messages`); tooltip toggle sets `aria-expanded`.
- [ ] **Step 2: Run** → FAIL. **Step 3: Implement**
  - Inline validation: on blur + on submit-attempt, for visible required/invalid fields render `.apo-error` under the input with `aria-invalid="true"` + `aria-describedby`; on submit-attempt with errors, focus the first invalid field (prevents the silent scroll-to-top); server-side validation stays authoritative (unchanged).
  - Price suffixes come from PHP (localization-safe, uses the existing currency formatter) — JS only recomputes the breakdown, not label suffixes.
  - **Swatch/date CSS upgrade (active work, not a verify — the 1.x styling in `assets/css/frontend.css:32-84` is untouched by earlier chunks):** swatches get larger touch targets (min 28px dot), a visible `:checked` ring (2px offset outline, non-color indicator per a11y), hover scale, and label alignment; date inputs get consistent height/padding with the text inputs. File/quantity styling was created new in Chunk 3 — verify only.
  - Focus-visible rings + `wp_style_add_data( …, 'rtl', 'replace' )` already in place — verify the new CSS builds an RTL variant (`npm run build`, check `build/frontend-rtl.css` diff non-empty).
- [ ] **Step 4: Run** — suites PASS; build. **Step 5: Live QA:** tooltip keyboard toggle; counter live; submitting with empty required shows inline error + focus (no page jump); RTL smoke via a real RTL site locale (Settings → Site Language → العربية, so WP serves `frontend-rtl.css`) or inspector `dir=rtl`.
- [ ] **Step 6: Commit** — `"feat: storefront polish — price suffixes, tooltips, counters, inline validation, focus/RTL (spec §9)"`.

**Chunk 4 exit criteria:** breakdown box correct for simple + variable products (server parity proven in cart); polish behaviors live-verified; all suites green.

---

## Chunk 5: Templates, import/export, release 2.0.0 (spec §8, §10–11)

### Task 5.1: Export / import REST + normalization

**Files:**
- Create: `includes/Admin/ImportExport.php` (pure `package( array $groups ): array` / `unpack( array $json ): array{groups: array[], warnings: string[]}`), wire routes into `GroupsRestController` (`GET /export?ids=`, `POST /import`)
- Test: `tests/php/Unit/ImportExportTest.php`

- [ ] **Step 1: Failing tests** — `package` shape `{version:'2.0', groups:[{title,fields,assignment,priority}]}` (no ids); `unpack` drops unknown field types/price modes into `warnings[]` (uses `FieldSchema::normalize` + reports what was removed), missing keys defaulted, non-array input → error entry; round-trip `unpack(package(x))` preserves normalized content.
- [ ] **Step 2: Run** → FAIL. **Step 3: Implement** (import handler: every unpacked group → `repo->save(0, …)` with `status: draft`; response `{created: ids[], warnings}`).
- [ ] **Step 4: Run** — PASS. **Step 5: Commit** — `"feat: group export/import with schema-normalizing warnings (spec §8)"`.

### Task 5.2: Built-in templates + hub screens (incl. import/export UI)

**Files:**
- Create: `includes/Templates/{gift-options,engraving,tshirt-designer,made-to-order,delivery-date,donation}.json` (each a single-group package: §8 list — engraving uses per_char; made-to-order uses quantity + formula `{width}*{height}*…`; tshirt uses image swatch + buttons; donation uses price+number), `includes/Templates/Templates.php` (loader: id, name, description, package), route `GET /templates` + `POST /templates/{id}/use` (import-as-draft → returns new group id)
- Create: `src/hub/screens/Templates.jsx` (the `#/templates` placeholder from 2.5 lives inline in the router — this file is NEW: card grid — name, description, contained field-type chips, "Use template" → navigate to `#/groups/{id}`)
- Modify: `src/hub/screens/GroupsList.jsx` — **import/export admin surface (spec §8 is NOT REST-only):** per-row **Export** action (GET /export?ids={id} → trigger a JSON file download) + header **Export all** and **Import** (file input; POST /import; show `created` count + `warnings[]` notices, navigate to list refresh)
- Test: `tests/php/Unit/TemplatesTest.php` — every shipped JSON unpacks with ZERO warnings (guards template↔schema drift)

- [ ] **Step 1: Failing test** — zero-warning loop over the 6 template files.
- [ ] **Step 2: Run** → FAIL.
- [ ] **Step 3: Author the 6 JSONs + loader + routes.**
- [ ] **Step 4: Build the Templates screen + GroupsList import/export controls.**
- [ ] **Step 5: Run** — `composer test` + `npm run test:js` + build → PASS.
- [ ] **Step 6: Live QA:** use "T-shirt designer" → builder opens seeded; storefront renders it after publish+assign; export a group → re-import it → appears as draft with warnings-free notice.
- [ ] **Step 7: Commit** — `"feat: 6 starter templates + hub Templates screen + import/export UI (spec §8)"`.

### Task 5.3: Full regression + live QA matrix (spec §10)

- [ ] **Step 1: Suites** — `composer test` + `npm run test:js` → ALL PASS; record counts in the commit message of 5.4.
- [ ] **Step 2: wp-env live matrix (Playwright MCP or manual):**
  1. Migration: seed a product with 1.x `_clpo_field_group` meta + `clpo_version=1.0.0` → admin load → group created, storefront identical, cart price unchanged.
  2. Global + product group on one product: both render, breakdown sums both, cart total matches.
  3. Variable product percent-mode parity (4.2 case).
  4. File upload end-to-end + cart-removal token release.
  5. Metabox: summary rows, Edit ↗ deep link, create-for-product flow.
  6. Legacy cart session: with a 1.x-shaped session entry (inject via wp-cli `wp wc …` or a scripted session fixture), cart renders + prices without fatals.
- [ ] **Step 3: Plugin Check** — run the wp.org Plugin Check plugin in wp-env → code-level clean (dev files excluded by `.distignore`).

### Task 5.4: Release packaging 2.0.0 (spec §11)

**Files:**
- Modify: `corelabs-product-options.php` (header `Version:` AND `CLPO_VERSION` → 2.0.0 — both were left at 1.0.0 until now, migration used the literal), `readme.txt` (rewrite: 100% free positioning, 18 types, global groups, breakdown, templates; FAQ refresh; changelog 2.0.0; Stable tag 2.0.0; Tested up to = current WP; Screenshots section gains a 3rd caption for the breakdown box), `bin/build-dist.sh` (verify Pro step removal from 1.1), `bin/svn-deploy.sh` (its assets `cp` list hardcodes screenshot-1/2 — ADD `screenshot-3.png`), `languages/corelabs-product-options.pot` (regen)

- [ ] **Step 1:** readme rewrite + version alignment (header == stable tag == CLPO_VERSION == composer.json version == 2.0.0).
- [ ] **Step 2:** `wp i18n make-pot . languages/corelabs-product-options.pot --domain=corelabs-product-options --exclude=node_modules,vendor,build,dist,tests,docs,bin,.wordpress-org,.superpowers`
- [ ] **Step 3:** `bash bin/build-dist.sh` → single free zip; inspect: `unzip -l dist/corelabs-product-options.zip` — src/ + composer.json present; no Pro/dev leaks; no `conditional-product-options` strings.
- [ ] **Step 4:** fresh listing screenshots (hub list, builder, storefront breakdown) → `.wordpress-org/` (keep 1544×500 banner; regenerate screenshot-1/2/3 via wp-env + Playwright at 1280×800) + update `bin/svn-deploy.sh` to copy `screenshot-3.png` (it currently copies only 1–2 and would silently drop the breakdown-box shot — the headline 2.0 visual).
- [ ] **Step 5: Commit** — `"release: 2.0.0 — readme, pot, dist, listing screenshots"`.
- [ ] **Step 6: Ship (USER-GATED — do not run unattended):** `bash bin/svn-deploy.sh` → review `svn status` → user runs the printed `svn ci` (wp.org credentials); GitHub push `git push origin main` (private repo); demo site update per `~/alovio-demo` provisioning; alovio.org copy already lists the plugin as free (verified in store.ts).

**Chunk 5 exit criteria:** dist zip clean; QA matrix all green; SVN trunk+tags/2.0.0 published (user-executed); plan checkboxes complete.
