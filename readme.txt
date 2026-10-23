=== Oli Import Sync for TranslatePress ===
Contributors: bigrat95
Donate link: https://www.paypal.com/donate/?hosted_button_id=29SAPKFAHMTNW
Tags: translatepress, multilingual, translation, woocommerce, wp-all-import
Requires at least: 5.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 3.17.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Import multilingual content into TranslatePress when using WP All Import. Third-party integration; not affiliated with TranslatePress.

== Description ==

**Oli Import Sync for TranslatePress** lets you import multilingual content directly into TranslatePress when using WP All Import. Ideal for bulk-importing products, posts, or any content that already has translations in your CSV/XML.

= Features =

* **Title, Content & Excerpt** - Import translated titles, descriptions, and excerpts
* **Post / Product Slugs** - Import or auto-derive translated post and product slugs from the translated title (requires SEO Pack)
* **WooCommerce Categories** - Import translated category names and slugs
* **WooCommerce Attributes** - Import translated attribute values (colors, sizes, etc.)
* **Variation Descriptions** - Import translated variation descriptions
* **Variable Products** - Full support for WooCommerce variable products and variations
* **Multiple Languages** - Import all your languages simultaneously
* **Human Reviewed Status** - Translations are saved as "Human Reviewed" (status 2) so auto-translation will not overwrite them
* **Automatic Paragraph Stripping** - Paragraph breaks are flattened into a single text block so TranslatePress always matches one string per description

= Requirements =

* WP All Import Pro
* TranslatePress Multilingual
* TranslatePress Custom API plugin (provided by TranslatePress support)
* TranslatePress SEO Pack (optional, for slug translations)

= How It Works =

1. Map your default language fields normally in WP All Import.
2. Add custom fields for translated content (e.g., `_trp_title_fr_CA`).
3. Run the import — translations are automatically synced to TranslatePress.
4. Temporary custom fields are cleaned up after import.

= Disclaimer =

This is an unofficial integration plugin. "TranslatePress" is a trademark of Cozmoslabs and is referenced here only for compatibility purposes. This plugin is developed independently and is not endorsed by, sponsored by, or affiliated with Cozmoslabs.

== External services ==

This plugin does **not** send import data, post content, or translations to any remote service. Translation writes stay on your WordPress site (TranslatePress tables / Custom API).

= PayPal Donate =

Optional **Donate** / **Buy me a coffee** links (Plugins screen, settings screen, and this plugin page) open PayPal in a new tab when you click them. Nothing is sent until you click.

* Destination: `https://www.paypal.com/donate/` (hosted button)
* Data sent: whatever PayPal collects on that page after you choose to donate
* Service: [PayPal](https://www.paypal.com/)
* Terms: [https://www.paypal.com/webapps/mpp/ua/useragreement-full](https://www.paypal.com/webapps/mpp/ua/useragreement-full)
* Privacy: [https://www.paypal.com/webapps/mpp/ua/privacy-full](https://www.paypal.com/webapps/mpp/ua/privacy-full)

= Author website =

The plugin header and documentation link to [https://olivierbigras.com](https://olivierbigras.com) and [https://github.com/olivierbigras/oli-import-sync-for-translatepress](https://github.com/olivierbigras/oli-import-sync-for-translatepress). These are ordinary hyperlinks; the plugin does not call those hosts in the background.

== Installation ==

1. Upload the `oli-import-sync-for-translatepress` folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Ensure TranslatePress and TranslatePress Custom API are also active
4. Configure your import in WP All Import with the translation custom fields

== Frequently Asked Questions ==

= What custom fields should I use? =

**For posts/products:**
* `_trp_title_[lang]` - Translated title
* `_trp_content_[lang]` - Translated content/description
* `_trp_excerpt_[lang]` - Translated excerpt (optional)
* `_trp_slug_[lang]` - Translated post/product slug (optional, requires SEO Pack). If empty and "Auto-derive post slug from translated title" is enabled, the slug is built from `_trp_title_[lang]`.

**For WooCommerce categories:**
* `_trp_cat_[lang]` - Translated category names (pipe-separated)
* `_trp_cat_slug_[lang]` - Translated category slugs (requires SEO Pack)

**For WooCommerce variation descriptions:**
* `_variation_description` - Default language variation description (standard WooCommerce field)
* `_trp_variation_desc_[lang]` - Translated variation description

**For WooCommerce attributes:**
* `_trp_attr_[attribute-slug]_[lang]` - Translated attribute values (pipe-separated)

Replace `[lang]` with your language code (e.g., `fr_CA`, `es_ES`, `de_DE`).

= What happens to existing translations? =

They are updated/overwritten with the new translations from your import. Post slug translations are also force-replaced (TranslatePress' own slug insert uses INSERT IGNORE, so the plugin clears the existing row first).

= Will auto-translation overwrite my imported translations? =

No. Imported translations are saved with status 2 (Human Reviewed), which protects them from being overwritten by automatic translation.

= How do I import multiple categories or attribute values? =

Use pipe-separated values. Example: `_trp_cat_fr_CA` = "Chaises|Tables|Bureaux"

= Do I need the SEO Pack for slug translations? =

Yes. The TranslatePress SEO Pack add-on is required for any slug translation (post, product, or category). Title, content, excerpt, attribute and category-name translations work without it.

= Is this plugin affiliated with TranslatePress / Cozmoslabs? =

No. This is an independent third-party integration. "TranslatePress" is referenced for compatibility only.

== Screenshots ==

1. Admin notice showing available custom fields
2. WP All Import custom field mapping
3. Translations synced in TranslatePress

== Changelog ==

= 3.17.4 =
* FIX: Normalize WP All Import hook post IDs (int, numeric string, or `WP_Post`) so PHP no longer warns when a post object is passed

= 3.17.3 =
* FIX: Normalize only posts collected on `pmxi_saved_post`; do not treat the WPAI import ID as a post
* FIX: Store the import log under uploads (`oli-import-sync/`, random name, directory deny)
* FIX: Remap 3.17.0–3.17.1 activations to `wpai-translatepress-sync.php` before header validation

= 3.17.2 =
* FIX: Restore the 3.16.2 main file (`wpai-translatepress-sync.php`) so an in-place update does not deactivate the plugin
* SQL identifiers use `%i` (WordPress 6.2+) or `$wpdb->prefix` tables only
* Ship `languages/oli-import-sync-for-translatepress.pot`

= 3.17.1 =
* Translators comment on the Field Reference sprintf; remaining `sprintf( __( … ) )` calls already documented
* Removed unprefixed `wpai_trp_*` helper wrappers (use `oli_import_sync_trp_*`)
* Write logs only when plugin logging is enabled or `WP_DEBUG_LOG` is active
* Shim reads the raw `active_plugins` list (unfiltered) and rewrites only this plugin's path
* Distribution ZIP excludes development files via `.distignore` / `export-ignore`

= 3.17.0 =
* Main plugin file is now `oli-import-sync-for-translatepress.php` (matches the slug). `wpai-translatepress-sync.php` remains as a header-less shim so existing sites stay active
* Escaped dashboard status output; admin CSS/JS live in files (no inline script/style or onclick handlers)
* Options migrated from `wpai_trp_*` to `oli_import_sync_trp_*` (old keys copied then deleted)
* Class renamed to `Oli_Import_Sync_For_TranslatePress`; helper functions prefixed `oli_import_sync_trp_*`
* Settings page slug: Settings → Oli Import Sync (`oli-import-sync-trp`); old `wpai-trp-sync` bookmarks redirect
* Tested up to WordPress 7.1

= 3.16.2 =
* NEW: Settings and Donate links on the Plugins screen, optional support notice on the settings screen, footer donation link, and Donate link on the WordPress.org plugin page (PayPal)

= 3.16.1 =
* FIX: Contributors field now uses the WordPress.org username (`bigrat95`) so the directory import no longer ignores it

= 3.16.0 =
* NEW: Mirror WordPress's `-2`, `-3` slug auto-disambiguator from the EN slug onto the auto-derived translated slug
* Prevents TranslatePress' `make_slugs_unique()` from clobbering trailing numbers in titles (e.g. "...inoxydable-304" no longer becomes "...inoxydable-305")
* Only triggers when EN's `-N` is a real WP duplicate suffix (another post owns the base slug) and N is 2..999
* No-op for explicit `_trp_slug_[lang]` mappings — those still win
* Variations (`product_variation`) continue to be skipped (they have no public URL)

= 3.15.0 =
* NEW: Post / product slug translation support via `_trp_slug_[lang]`
* NEW: "Auto-derive post slug from translated title" setting (default ON) — builds the translated slug from `_trp_title_[lang]` when no explicit slug is provided
* Force-replace of existing post slug translations on re-import (TranslatePress' INSERT IGNORE no longer blocks updates)
* Field Reference and Dashboard updated to surface the new slug field and toggle

= 3.14.0 =
* Renamed plugin to "Oli Import Sync for TranslatePress" (trademark-friendly; not affiliated with TranslatePress)
* Text domain: `oli-import-sync-for-translatepress`
* Admin: enqueue CSS/JS via `wp_enqueue_style` / `wp_enqueue_script` + inline (no raw style/script tags)
* Description clarifies third-party integration

= 3.13.0 =
* FIX: Strip ALL paragraph separators into a single text block (spaces only, no `<br>`)
* `wpautop()` wraps in ONE `<p>` = TranslatePress detects ONE string = dictionary always matches
* Works regardless of paragraph count differences between languages
* Re-enabled content flattening hook and safety-net DB update

= 3.12.0 =
* Per-paragraph translation matching (superseded by 3.13.0)
* Removed content flattening (was causing mismatch between DB content and what TranslatePress detects)
* Variation descriptions also use per-paragraph matching

= 3.11.0 =
* NEW: Full plugin dashboard under Settings → TP Import Sync
* Tabbed interface: Dashboard, Field Reference, Logs
* Auto-detects TranslatePress languages, WooCommerce attributes, and all available translation fields
* Copy-to-clipboard button on every field name

= 3.10.0 =
* Admin settings page with log viewer and logging toggle

= 3.9.0 =
* FIX: Content normalization now persists in database (direct DB update bypasses WooCommerce hooks)
* Added safety-net normalization on `pmxi_after_post_import`

= 3.8.1 =
* FIX: Variation description translations now always normalized
* FIX: post_excerpt normalization now also updates the DB

= 3.8.0 =
* Bulletproof content flattening: handles `<p>`, `<div>`, Gutenberg blocks, all `<br>` variants, `&nbsp;` spacers

= 3.7.0 =
* Auto-normalize paragraph breaks (replaces manual `_trp_convert_linebreaks` field)

= 3.6.0 =
* FIX: Multi-paragraph descriptions now translated correctly

= 3.5.0 =
* FIX: Handle WordPress `wptexturize()` smart quotes in translation matching

= 3.4.0 =
* Keep variation description translation meta for direct theme lookup

= 3.3.0 =
* FIX: Attribute translation for variations now correctly matches HTML entities

= 3.1.0 =
* NEW: Variation description translation support (`_trp_variation_desc_[lang]`)

= 3.0.0 =
* NEW: Full support for WooCommerce variable products and variations
* Force update mode: existing translations are always overwritten

= 2.3.2 =
* Added upgrade notice section

= 2.3.1 =
* Renamed plugin (text domain aligned with plugin slug)
* Updated tested up to WordPress 6.9

= 2.3.0 =
* NEW: WooCommerce category and attribute translation support

= 2.2.0 =
* Line break conversion is now optional (deprecated in 3.7.0)

= 2.1.0 =
* Added automatic line break conversion

= 2.0.0 =
* Rewrote to use official TranslatePress Custom API
* Added support for multiple languages simultaneously

= 1.0.0 =
* Initial release

== Upgrade Notice ==

= 3.17.4 =
Fixes PHP warnings during WP All Import when a post object is passed instead of a post ID.

= 3.17.3 =
Keeps the plugin active from 3.16.2, 3.17.0, and 3.17.1. Import log moves to uploads. Import IDs are no longer treated as post IDs.

= 3.17.2 =
Keeps the plugin active when updating from 3.16.2 (same main file name). Options are unchanged.

= 3.17.1 =
Plugin Check / logging / shim follow-up. Use `oli_import_sync_trp_*` helpers. Existing activations still load through the header-less shim.

= 3.17.0 =
Main file renamed to match the plugin slug. Existing activations keep working via a compatibility shim; settings are copied to the new option names automatically.

= 3.16.2 =
Adds Donate / support links (Plugins screen, settings notice, WordPress.org Donate link).

= 3.16.1 =
Fixes the WordPress.org Contributors field (must be a wordpress.org username). No functional changes.

= 3.16.0 =
Auto-derived translated slugs now mirror WordPress's `-2`, `-3` disambiguator from the EN slug, fixing cases where TranslatePress would alter trailing numbers in titles (e.g. "304" -> "305").

= 3.15.0 =
Adds translated post / product slug support. Auto-derive slugs from translated titles, or set them explicitly via `_trp_slug_[lang]`. Requires SEO Pack.

= 3.14.0 =
Plugin renamed to "Oli Import Sync for TranslatePress" (trademark-friendly). No breaking changes for imports.

= 3.13.0 =
Strip all paragraph breaks into one text block. Translation always matches regardless of paragraph count.
