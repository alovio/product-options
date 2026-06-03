# WordPress.org Plugin Review — Cavab & Action Plan

> **Mənbə:** WordPress.org Plugin Directory review emaili
> **Tarix:** 2026-06-02 06:27 UTC
> **Status:** ⏸️ *Review in Progress (pended)* — düzəlişlər edilib yenidən upload + email-ə reply tələb olunur

## Plugin metadata (review-dan)

| Sahə | Dəyər |
|------|-------|
| Display name | Conditional Product Options for WooCommerce |
| Slug | `conditional-product-options` |
| Author | CoreLabs |
| Author URI | https://addons.itahir.com |
| Plugin URI | https://addons.itahir.com/conditional-product-options |
| Contributors | `corelabs` |
| WP.org username | `74h1r` |
| Email | ttaxiir@gmail.com |
| Review ID | `AUTOPREREVIEW ❗TRM-OWN conditional-product-options/74h1r/2Jun26/T1 2Jun26/4.0.1 (P0TDX321625HGN)` |

Bu, əsasən avtomatik (human + AI) pre-review-dur. AI ilə yaradılan hissələr ✨ ilə işarələnib. Cavab **qısa və konkret** olmalıdır (uzun AI mətnləri istəmirlər).

---

## 🔴 Bloklayıcı problemlər (mütləq həll edilməli)

### 1. Plugin adı / trademark — *generic + "WooCommerce" trademark*
- AI "WooCommerce"-i potensial trademark kimi tutub və adı həm **çox generic**, həm də mövcud *"... Product Options for WooCommerce"* pluginlərinə **çox yaxın** hesab edir.
- Qayda: distinctive **brand/coined termin adın ƏVVƏLİNDƏ** olmalı; trademark yalnız sonda `for`/`with` strukturu ilə işlənə bilər. Sadəcə bir söz (Advanced, Easy, Simple) və ya hərf əlavə etmək **işləmir**.
- AI təklifi (məcburi deyil, ilham üçün):
  - Ad: ✨ *Itahir Product Options with Conditional Logic for WooCommerce*
  - Slug: ✨ `itahir-product-options-conditional-logic`

**☑️ Addımlar:**
- [ ] Distinctive yeni display name seç (brand termini əvvəldə, məs. `Itahir`/`CoreLabs`/coined söz). Google/DuckDuckGo-da yoxla ki, oxşar plugin olmasın.
- [ ] Yeni adı `readme.txt` başlığında **və** `conditional-product-options.php` header-ində yenilə.
- [ ] Slug-u kod boyu yenilə: **text domain** + bütün `__()/_e()` və s. i18n funksiyalarındakı domain arqumenti + asset handle-ları (`wp_register_script`/`wp_enqueue_*`).
- [ ] **Text domain dəyişdiyi üçün:** `.pot` faylını yenidən generasiya et (`languages/<yeni-slug>.pot`) + `wp_set_script_translations()` handle/path-ini yenilə + `Text Domain:`/`Domain Path:` header-ini yoxla.
  *(ⓘ Yeni versiyanı upload edəndə müvəqqəti `textdomain_mismatch` xəbərdarlığı görə bilərsən — onlar slug-u öz tərəflərində hələ dəyişmədiyi üçün. Email deyir bu **normaldır**, narahat olma.)*
- [ ] Email-ə reply edib **yeni slug rezervasiyası** açıq tələb et (yalnız kodda dəyişmək kifayət deyil; yeni slug-un dəqiq adını yaz; permalink approval-dan sonra dəyişmir).
- [ ] Yeni versiyanı "Add your plugin" səhifəsindən upload et.
- [ ] Plugin icon/banner, **bütün URL-lər (Plugin URI / Author URI)**, WP.org **username `74h1r`** və display name-də də trademark/oxşarlıq istifadəsini yoxla.

### 2. Mülkiyyət / kimlik təsdiqi — *gmail.com qəbul olunmur*
- Author=`CoreLabs`, Author URI=`addons.itahir.com`, amma:
  - 🟧 `74h1r` username readme-dəki contributors (`corelabs`) ilə uyğun gəlmir.
  - 🟥 Email domeni `gmail.com` plugin-dəki URL/brand/trademark ilə əlaqəli deyil → mülkiyyət təsdiqi üçün **yararsız**.

**☑️ Aşağıdakılardan birini et (ən asanı bizim üçün — DNS):**
- [ ] **DNS TXT (tövsiyə):** `itahir.com` (owner domeninin kökü `@`) üçün TXT record əlavə et:
  `wordpressorg-74h1r-verification`
  *(Qeyd: yalnız `addons.itahir.com`-un kök domeni `itahir.com`-da etmək lazımdır; subdomain yox.)*
- [ ] **VƏ YA** WP.org email-ini domen email-inə dəyiş (məs. `*@itahir.com`) — WP.org profilindən özün etməlisən, onlar edə bilmir.
- [ ] **VƏ YA** adı/slug-u açıq şəkildə "qeyri-affiliated" edib reply ilə izah et.
- ⚠️ **Plugini başqa hesabla yenidən submit ETMƏ** — hər iki submission rədd olunar.

### 3. Prefiks problemi — *`APO` cəmi 3 simvoldur* 🔴
- Qayda: prefiks **ən azı 4 simvol**, distinctive və unikal olmalı, generic söz olmamalı.
- Hazırda kodda: `define( 'APO_VERSION' )`, `APO_FILE`, `APO_PATH`, `APO_URL`, namespace `\APO\Plugin` → **`APO` = 3 simvol**, həm qısa, həm generic.

**☑️ Addımlar:**
- [ ] `APO_*` constant-ları ≥4 simvol distinctive prefiksə dəyiş (məs. yeni brendlə uyğun: `CONDPROP_`, `ITPOPT_` və s.).
- [ ] `\APO\` namespace-ini eyni şəkildə yenilə (`composer.json` autoload + bütün `use`/class referansları).
- [ ] **Namespace-dən kənar** istənilən qlobal **funksiya** və ya **class** varsa (helper-lər və s.) onları da prefiksə (məs. `condprop_*` / `CONDPROP_*`).
- [ ] **Plugin-in öz custom hook-ları** da `apo_` (3 simvol) daşıyır → bunları da ≥4 simvol prefiksə dəyiş: `apo_field_types`, `apo_is_pro`, `apo_addon_total`, `apo_price_modes`, `apo_multi_conditions`, `apo_allowed_operators/actions` və s. *(Diqqət: bunlar Pro add-on plugin ilə paylaşılan extension-point-lərdir — adı dəyişəndə Pro tərəfi də sinxron yenilənməlidir, yoxsa gate işləməz.)*
- [ ] Bunları da yoxla: `update_option()`/`get_option()`, `set_transient()`, `update_post_meta()` meta açarları (məs. `_apo_field_group` → yeni prefiks!), `add_shortcode()`, `register_post_type()`, `register_setting()`, `add_menu_page()`, `wp_register_script()`/`wp_localize_script()` handle-ları, `wp_ajax_*` action adları, `define()` constant-lar, global dəyişənlər.

### 4. Mənbə kodun açıqlığı (Guideline 1 & 4) — *build faylları üçün source yoxdur*
- Review `build/frontend.js` və `build/index.js`-i minified/compiled kimi tutub və ZIP-də uyğun human-readable source tapmayıb, readme-də public repo linki də yoxdur.
- **Səbəb:** `.distignore` faylda `/src` distributiv ZIP-dən çıxarılıb → buildə uyğun mənbə paketdə yoxdur.

**☑️ Aşağıdakılardan birini et:**
- [ ] **Variant A:** `readme.txt`-ə public **source repo** linki əlavə et (məs. açıq GitHub repo) + build addımlarını (`npm`, webpack) sənədləşdir. *(Pro kodu repodan kənarda saxla.)*
- [ ] **Variant B:** `/src`-i distributiv ZIP-ə daxil et (`.distignore`-dan `/src` sətrini çıxar) və readme-də build prosesini izah et.
- [ ] Üçüncü tərəf JS/library-lər varsa (həm `vendor/`, həm `build/`), onların adı/versiya/repo URL-ini faylda və ya readme-də sənədləşdir.
- [x] **GPL lisenziya (Guideline #1):** header-də artıq `GPL-2.0-or-later` var → bu hissə **OK**, ayrıca iş tələb etmir (yalnız obfuscation/source açıqlığı hissəsi qalır).

---

## 🟠 Kiçik / "Other details"

### 5. Composer — *`composer.json` ZIP-də tapılmadı*
- `vendor/autoload.php` plugin-də require olunur, amma `.distignore` `/composer.json` və `/composer.lock`-u ZIP-dən çıxarır → tool "composer.json not found in `conditional-product-options/composer.json`" deyir.
- [ ] `.distignore`-dan `/composer.json` (ən azı) sətrini çıxar ki, distributiv ZIP-ə daxil olsun (development məqsədli olsa belə tövsiyə olunur).

### 6. Contributors siyahısı
- readme-də `Contributors: corelabs`, amma faktiki WP.org hesabı `74h1r`-dir. Review: *"None of the listed contributors 'corelabs' is the WordPress.org username of the owner '74h1r'."*
- [ ] Ya `74h1r`-i contributors-a əlavə et (`Contributors: 74h1r` və ya `corelabs, 74h1r`), ya da `corelabs` adlı real WP.org hesabını yarat/sahiblən. Owner hesabı ilə contributors uyğun olmalıdır.

---

## ✅ Reply checklist (göndərməzdən əvvəl)

- [ ] Bütün yuxarıdakı problemlər həll edilib.
- [ ] Plugin lokal test edilib — **aktivləşməsi fatal error vermir**.
- [ ] Yeni versiya "Add your plugin" səhifəsindən upload edilib (login: `74h1r`).
- [ ] Email-ə reply edilib (**qısa və konkret** — dəyişikliklərin siyahısını yox, vacib kontekst/izahları yaz; slug dəyişdisə yeni slug-u açıq de).

> ⏳ Əgər 3 ay ərzində adekvat irəliləyiş olmasa submission rədd edilir. Rədd olunarsa eyni plugin bir daha review edilmir.

---

## 📧 Tam email (verbatim, istinad üçün)

**From:** WordPress.org Plugin Directory \<plugins@wordpress.org\>
**To:** 74h1r \<ttaxiir@gmail.com\>
**Date:** Tue, 02 Jun 2026 06:27:41 +0000
**Subject:** [WordPress Plugin Directory] Review in Progress: Conditional Product Options for WooCommerce

```
-- Please reply above this line --

            👋 74h1r - Let’s improve your plugin!

Thank you for submitting your plugin, "Conditional Product Options for WooCommerce".

Our volunteer reviewers, tools, and/or AI aids identified issues in your plugin
that require your attention.

We’ve pended your submission to give you a chance to review and fix these common
issues.

This team handles approximately 1,500 plugin reviews each week. That's a lot. To
make the most of this process, please do your part and help us help you;
otherwise, your plugin won't be approved.

🤖 Please note that this message was generated using a combination of humans,
algorithms, and AI in varying proportions. It may not have been reviewed by a
human. All AI outputs are marked with the ✨ emoji. Pay attention to it, it's
quite accurate.

--- HAVE YOU READ THE GUIDELINES? ---

Developers must provide public, maintained access to their source code and any
build tools in one of the following ways: (1) Include the source code in the
deployed plugin, (2) A link in the readme to the development location.
(Guidelines 1, 4)

--- IS THE NAME DESCRIPTIVE AND DISTINCTIVE? ---

This plugin display name is "Conditional Product Options for WooCommerce" and the
slug is "conditional-product-options". The AI has detected ✨ "WooCommerce" as
potential trademark(s).

✨ The display name is descriptive but generic, does not start with a distinctive
brand term, and is very close to existing plugin names built around "Product
Options for WooCommerce," which can cause confusion.

    Alternative name: ✨ Itahir Product Options with Conditional Logic for WooCommerce
    Alternative slug: ✨ itahir-product-options-conditional-logic

Steps to resolve:
  - Update the display name in both the readme file and plugin headers.
  - Update the slug in your plugin files (e.g. internationalization functions).
  - Reply to this email requesting a new slug reservation.
  - Upload a new version via the "Add your plugin" page.

Also check: your username (74h1r), the contributor's username/display name, the
plugin URLs, and graphic resources (icons/banners) for trademarked terms.

Naming examples:
  ❌ "WooCommerce Prices Updater"        — implies affiliation
  ❌ "Prices Updater WooCommerce"        — no clear unaffiliation structure
  ❌ "Prices for WooCommerce"            — too generic
  ❌ "Prices for WooCommerce by 74h1r"   — distinguishing term must be at the start
  ❌ "AB Prices for WooCommerce"         — a few letters isn't differentiating
  ❌ "PricesPress for WooCommerce"       — portmanteau of a trademark
  ❌ "Prices Updater for WooCommerce"    — similarity with other plugins
  ❌ "Easy Prices Updater for WooCommerce" — generic word doesn't fix similarity
  ✅ "Priconix Sync for WooCommerce"     — original, distinguishable, unique
  ✅ "74h1r Prices Updater for WooCommerce" — unique identifier + unaffiliation

--- ARE YOU THE RIGHTFUL OWNER? ---

This is what we know about this plugin:
  Plugin name (readme.txt): "Conditional Product Options for WooCommerce"
  Plugin name (.php file):  "Conditional Product Options for WooCommerce"
  Slug: conditional-product-options
  Author: CoreLabs
  Author URI: https://addons.itahir.com
  Plugin URI: https://addons.itahir.com/conditional-product-options
  Contributors: corelabs

This is what we know about you:
  Username: 74h1r
    🟧 Your username does not match any of the contributors declared in the plugin.
  Email: ttaxiir@gmail.com
    🟥 Your email domain "gmail.com" does not seem related to any of the URLs,
       names, trademarks and/or services declared in the plugin.
    🟥 A gmail.com account cannot be used as a valid form of identification.

You can demonstrate ownership in one of the following ways:
  📩 Update your WordPress.org email to one under the entity's domain (do it in
     your WordPress.org profile — we cannot do it for you).
  👤 Reply asking us to transfer this submission to the correct WordPress.org
     account (tell us the username). Do NOT resubmit with the new account.
  🛠 Change the display name and slug to make non-affiliation clear, then upload.
     Reply asking us to change the slug.
     Reply clarifying the situation (e.g. same entity, or you already have
     established plugins under the same account = tacit verification).
  🔌 Perform a DNS check: add a TXT record at the owner's domain root @ with value:
       wordpressorg-74h1r-verification

⚠️ Do not resubmit this plugin using a different account — both submissions will
be rejected and accounts may be suspended.

--- COMMON TECHNICAL ISSUES ---

🔴 Use Prefixes for declarations, globals and stored data
A prefix must be at least 4 characters long, feel distinct and unique to the
plugin (do not use common words), separated by underscore or dash.
Check: functions, classes (if not namespaced), global vars, namespaces, define(),
update_option(), set_transient(), update_post_meta(), add_shortcode(),
register_post_type(), add_menu_page(), wp_register_script(), wp_localize_script(),
add_action('wp_ajax_...'), etc.
Example for this plugin (suggested): condprop_save_post(), class CONDPROP_Admin,
update_option('condprop_options'), define('CONDPROP_PLUGIN_DIR', ...),
global $condprop_options, add_action('wp_ajax_condprop_save_data', ...),
namespace h1rconditionalproductoptions;

--- OTHER DETAILS ---

## You haven't added yourself to the "Contributors" list for this plugin.
Your username (74h1r) is not in the comma-separated Contributors list. Add
yourself if you want to be listed (not mandatory).

# WARNING: None of the listed contributors "corelabs" is the WordPress.org
username of the owner of the plugin "74h1r".

## The source code of your plugin should be publicly accessible.
Guideline #1: GPL-compatible license.
Guideline #4: code cannot be obfuscated; must provide public, maintained access
to source code and build tools.
Detected compressed/minified files generated by build tools (npm, webpack, etc.)
but could not match them to a non-compiled version:

  build/frontend.js:1  ...(()=>{"use strict";function e(e){return""!==e&&...
    ✨ Generated build/frontend.js is included without corresponding
       human-readable source files and without any public source repository
       referenced in the readme
  build/index.js:1     ...(()=>{"use strict";var e={n:t=>{...
    ✨ Generated build/index.js is included without corresponding human-readable
       source files and without any public source repository referenced in the readme

## Using composer but could not find composer.json file
  composer.json file not found in "conditional-product-options/composer.json"
  (Please include it, even if used only for development.)

--- YOUR NEXT STEPS ---
  1. Fix everything and test (no fatal error on activation).
  2. Update plugin files at the "Add your plugin" page (logged in as 74h1r).
  3. Reply to this email — short, direct, clear. Don't list the changes; share
     only important context/clarifications.

If you believe you cannot meet a requirement and choose not to change it, the
submission will be rejected after three months.

--- DISCLAIMERS ---
If you want to change the permalink/slug "conditional-product-options", you must
explicitly tell us the new value. Permalinks cannot be altered after approval.

Review ID: AUTOPREREVIEW ❗TRM-OWN
conditional-product-options/74h1r/2Jun26/T1 2Jun26/4.0.1 (P0TDX321625HGN)
--
WordPress Plugins Team | plugins@wordpress.org
https://make.wordpress.org/plugins/
https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/
https://wordpress.org/plugins/plugin-check/
```

---

## 🔗 İstinad linkləri

- Plugin Guidelines: https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/
- Avoiding name collisions (prefixing): https://developer.wordpress.org/plugins/plugin-basics/best-practices/
- Plugin Check tool: https://wordpress.org/plugins/plugin-check/
- Add/update your plugin: https://wordpress.org/plugins/developers/add/
