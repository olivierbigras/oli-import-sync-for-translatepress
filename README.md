# Oli Import Sync for TranslatePress

Import multilingual content into TranslatePress when using WP All Import. Third-party integration; not affiliated with TranslatePress / Cozmoslabs.

**Author:** Olivier Bigras  
**Website:** [olivierbigras.com](https://olivierbigras.com)  
**Email:** oli@olivierbigras.com  
**Version:** 3.17.1  
**License:** GPL v2 or later

---

## Requirements

- WordPress 5.0+
- [WP All Import Pro](https://www.wpallimport.com/)
- [TranslatePress Multilingual](https://translatepress.com/)
- [TranslatePress Custom API](https://translatepress.com/) (provided by TranslatePress support)

---

## Installation

1. Download or copy the `oli-import-sync-for-translatepress` folder
2. Upload to `wp-content/plugins/` 
3. Activate the plugin in WordPress → Plugins
4. Ensure TranslatePress and TranslatePress Custom API are also active

---

## How It Works

When you run a WP All Import, the plugin:

1. **Strips all paragraph breaks** from content into a single text block (no `<p>`, `<div>`, `<br>`, or newlines — only spaces)
2. Captures translated content from special custom fields you map
3. Handles smart quote mismatches (WordPress `wptexturize()` conversions)
4. Uses the official TranslatePress Custom API to insert translations
5. Marks translations as "Human Reviewed" (status 2)
6. Persists the flattened content to the database so `wpautop()` wraps it in ONE `<p>` tag and TranslatePress detects one string per description
7. Safety-net hook re-flattens content if WooCommerce or other plugins revert it
8. Cleans up the temporary custom fields after import

---

## WP All Import Configuration

### Step 1: Map Your Default Language Fields

Map your CSV/XML fields to the standard WordPress fields:

| WordPress Field | Your CSV Column |
|-----------------|-----------------|
| Title | English title |
| Content | English description |
| Excerpt | English short description |

### Step 2: Add Translation Custom Fields

In the **Custom Fields** section of your import, add fields using this naming pattern:

```
_trp_title_[LANGUAGE_CODE]
_trp_content_[LANGUAGE_CODE]
_trp_excerpt_[LANGUAGE_CODE]
```

Where `[LANGUAGE_CODE]` matches your TranslatePress configuration (e.g., `fr_CA`, `fr_FR`, `es_ES`, `de_DE`).

### Examples

For French Canadian (`fr_CA`):

| Custom Field Name | CSV Column |
|-------------------|------------|
| `_trp_title_fr_CA` | French title column |
| `_trp_content_fr_CA` | French description column |
| `_trp_excerpt_fr_CA` | French short description (optional) |

For Spanish (`es_ES`):

| Custom Field Name | CSV Column |
|-------------------|------------|
| `_trp_title_es_ES` | Spanish title column |
| `_trp_content_es_ES` | Spanish description column |

---

## Supported Fields

### Post/Product Fields

| Custom Field Prefix | Maps To |
|---------------------|---------|
| `_trp_title_` | Post title |
| `_trp_content_` | Post content (description) |
| `_trp_excerpt_` | Post excerpt (short description) |

### WooCommerce Category Fields

| Custom Field | Description |
|--------------|-------------|
| `_trp_cat_[lang]` | Translated category names (pipe-separated for multiple) |
| `_trp_cat_slug_[lang]` | Translated category slugs (requires SEO Pack) |

**Example:** `_trp_cat_fr_CA` = "Chaises\|Tables\|Bureaux"

### WooCommerce Variation Description Fields

| Custom Field | Description |
|--------------|-------------|
| `_variation_description` | Default language variation description (standard WooCommerce field) |
| `_trp_variation_desc_[lang]` | Translated variation description |

**Example:** `_trp_variation_desc_fr_CA` = "Description de la variation en français"

**Note:** This field is for product variations only (not the main product). Map `_variation_description` for the default language in WP All Import's Variations tab or Custom Fields section.

### WooCommerce Attribute Fields

| Custom Field | Description |
|--------------|-------------|
| `_trp_attr_[slug]_[lang]` | Translated attribute values (pipe-separated) |

**Examples:**
- `_trp_attr_color_fr_CA` = "Rouge\|Bleu\|Vert"
- `_trp_attr_size_fr_CA` = "Petit\|Moyen\|Grand"

**Note:** Works with both global attributes (taxonomy-based like `pa_color`) and local/custom product attributes.

---

## WooCommerce Variable Products

The plugin fully supports WooCommerce variable products imported with WP All Import.

### Supported Import Structures

**Option 1: Variations grouped by unique value** (Recommended)
- Use "All products with variations are grouped with a unique value that is the same for each variation and unique for each product"
- Each CSV row represents one variation
- Set `Unique Value` and `Parent SKU` to your parent SKU column
- Map individual variation SKUs in Inventory → SKU

**Option 2: Variations linked to parent SKU**
- Use "All my variable products have SKUs or some other unique identifier"
- Each variation is linked to its parent

### How It Works

1. When importing variations, the plugin automatically detects the parent product
2. Attribute terms are retrieved from the parent product
3. Translations are applied to the attribute terms in TranslatePress
4. Both simple products and variable products are supported

### Example CSV Structure

| SKU WEB | SKU WEB - PARENT | MAGNETIC - EN | MAGNETIC - FR |
|---------|------------------|---------------|---------------|
| BDC3232WT | BDC-R, BDC-L | Magnetic Drain Cover | Cache-drain magnétique |
| BDC3636WT | BDC-R, BDC-L | Magnetic Drain Cover | Cache-drain magnétique |

### Custom Fields Mapping

```
_trp_attr_magnetic_fr_CA = {magneticfr[1]}
```

The plugin will:
1. Detect this is a product variation
2. Get the parent product
3. Find the `pa_magnetic` attribute on the parent
4. Translate "Magnetic Drain Cover" → "Cache-drain magnétique"

---

## Multiple Languages

You can import multiple languages simultaneously! Just add custom fields for each language:

```
_trp_title_fr_CA    → French Canadian title
_trp_title_es_ES    → Spanish title
_trp_content_fr_CA  → French Canadian content
_trp_content_es_ES  → Spanish content
```

The plugin automatically detects all configured languages from TranslatePress.

---

## Automatic Content Normalization

TranslatePress detects each `<p>` block as a separate translatable string. Without normalization, a 3-paragraph description would require 3 separate dictionary entries — making translation matching unreliable, especially when the original and translated text have different paragraph counts.

Since v3.13.0, the plugin **strips ALL paragraph separators** and joins content with spaces into a single text block. `wpautop()` then wraps it in ONE `<p>` tag, so TranslatePress always detects ONE string = ONE dictionary entry.

### What It Strips

| Content Format | Action |
|---|---|
| `\n\n` (double newlines from CSV) | Replaced with space |
| `\n` (single newlines) | Replaced with space |
| `<p>...</p>` tags (from editors) | Stripped, replaced with space |
| `<div>...</div>` wrappers | Stripped, replaced with space |
| `<!-- wp:paragraph -->` (Gutenberg) | Stripped |
| `<br>`, `<br/>`, `<br />` variants | Replaced with space |
| `&nbsp;` entities | Replaced with space |
| Multiple consecutive spaces | Collapsed to one space |

### What It Preserves

- `<strong>`, `<b>` — bold text
- `<em>`, `<i>` — italic text
- `<a href="...">` — links
- `<span>` — inline styling
- `<ul>`, `<ol>`, `<li>` — lists
- All other inline HTML tags

### Result

- Your entire description is always ONE string in TranslatePress
- English and French translations match 1:1 regardless of paragraph count
- Works with content, excerpts, and variation descriptions
- No visual paragraph breaks (content renders as a single block of text)

### Example

CSV input (3 paragraphs in English, 4 paragraphs in French — doesn't matter):
```
Paragraph 1...

Paragraph 2...

Paragraph 3...
```

Saved in WordPress as:
```
Paragraph 1... Paragraph 2... Paragraph 3...
```

Rendered on frontend:
```html
<p>Paragraph 1... Paragraph 2... Paragraph 3...</p>
```

TranslatePress detects ONE string → dictionary matches → French version displays correctly.

> **Note:** The `_trp_convert_linebreaks` custom field from v2.x is no longer needed. Normalization always happens automatically.

---

## Admin Notice

When you're on a WP All Import page, you'll see a helpful notice showing:
- The custom field names to use
- Which languages are configured in TranslatePress

---

## Helper Functions

Use these functions in your theme or other plugins:

### Add a single translation

```php
oli_import_sync_trp_add_translation( 'Hello World', 'Bonjour le monde', 'fr_CA' );
```

### Bulk add translations

```php
$translations = array(
    'Hello' => 'Bonjour',
    'Goodbye' => 'Au revoir',
    'Welcome' => 'Bienvenue',
);

oli_import_sync_trp_bulk_add_translations( $translations, 'fr_CA' );
```

---

## TranslatePress Custom API Functions

This plugin uses the official TranslatePress Custom API:

```php
// Insert single translation
trpc_insert_translation( $original, $translated, $language, $args );

// Bulk insert
trpc_insert_translations_bulk( $translations, $language, $args );

// Get configured languages
trpc_get_languages();
// Returns: ['default' => 'en_CA', 'translations' => ['fr_CA'], 'all' => ['en_CA', 'fr_CA']]
```

---

## Debugging

The plugin has a built-in log viewer under **Settings → TP Import Sync → Logs**.

1. Enable logging on the Logs tab
2. Run your import
3. Check the log for entries like:
```
Flattened post_content for post #4497 (523 chars)
Added 1 translations for post #4497
SAFETY NET: Normalized post_content for post #4497
```

You can also enable WordPress debug mode for additional output in `wp-content/debug.log`:

```php
// In wp-config.php
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
```

---

## FAQ

### Q: What happens to existing translations?

**A:** They are updated/overwritten with the new translations from your import.

### Q: What status do imported translations get?

**A:** Status 2 (Human Reviewed), so they will **never** be overwritten by automatic translation. You can safely keep auto-translation enabled for other strings.

### Q: Can I keep automatic translation enabled?

**A:** Yes! Your imported translations are protected with status 2 (Human Reviewed). Automatic translation will only translate strings that don't have a human-reviewed translation.

### Q: Can I use this with WooCommerce products?

**A:** Yes! Full WooCommerce support including product categories and attributes.

### Q: How do I translate category names?

**A:** Add `_trp_cat_fr_CA` custom field with pipe-separated translated names matching the order of your original categories.

### Q: How do I translate product attributes?

**A:** Use `_trp_attr_[attribute-slug]_[lang]` format. For a "color" attribute: `_trp_attr_color_fr_CA` = "Rouge|Bleu"

### Q: Do I need to keep the temporary custom fields?

**A:** No, they are automatically deleted after the translation is synced.

---

## Changelog

### 3.17.1
- Translators comment on the Field Reference sprintf
- Removed unprefixed `wpai_trp_*` helpers (use `oli_import_sync_trp_*`)
- Logs only when plugin logging is on or `WP_DEBUG_LOG` is active
- Shim reads raw `active_plugins` and rewrites only this plugin's path
- `.distignore` / `export-ignore` for the distribution ZIP

### 3.17.0
- Main file renamed to `oli-import-sync-for-translatepress.php` (slug match). `wpai-translatepress-sync.php` is a header-less shim so existing activations are not dropped
- Dashboard status output escaped; admin CSS/JS enqueued from files
- Options migrated `wpai_trp_*` → `oli_import_sync_trp_*`
- Class `Oli_Import_Sync_For_TranslatePress`; helpers `oli_import_sync_trp_*`

### 3.16.2
- Settings and Donate links on the Plugins screen, optional support notice, footer donation link (PayPal)

### 3.16.0
- **NEW:** Mirror WordPress's `-2`, `-3` slug auto-disambiguator from the EN slug onto auto-derived translated slugs
- Prevents TranslatePress' `make_slugs_unique()` from clobbering trailing numbers in titles (e.g. "...-304" no longer becomes "...-305")
- Only triggers when EN's `-N` is a real WP duplicate suffix (another post owns the base slug) and N is 2..999; explicit `_trp_slug_[lang]` mappings still win

### 3.15.0
- **NEW:** Translated post / product slug support via `_trp_slug_[lang]` (requires SEO Pack)
- **NEW:** "Auto-derive post slug from translated title" setting (default ON)
- Force-replace existing post slug translations on re-import (TranslatePress' INSERT IGNORE no longer blocks updates)

### 3.14.0
- Renamed plugin to "Oli Import Sync for TranslatePress" (trademark-friendly)
- Text domain: `oli-import-sync-for-translatepress`

### 3.13.0
- **FIX:** Strip ALL paragraph separators into a single text block (spaces only, no `<br>`)
- `wpautop()` wraps in ONE `<p>` = TranslatePress detects ONE string = dictionary always matches
- Works regardless of paragraph count differences between languages
- Re-enabled content flattening hook and safety-net DB update

### 3.12.0
- Per-paragraph translation matching (superseded by 3.13.0)

### 3.11.0
- **NEW:** Full plugin dashboard under Settings → TP Import Sync
- Tabbed interface: Dashboard (system status), Field Reference (auto-detected fields with copy buttons), Logs
- Auto-detects TranslatePress languages, WooCommerce attributes, and all available translation fields
- WP All Import admin notice slimmed to a link to the dashboard

### 3.10.0
- Admin settings page with log viewer and logging toggle

### 3.9.0
- **FIX:** Content normalization now persists in database (direct DB update bypasses WooCommerce hooks)
- Added safety-net normalization on `pmxi_after_post_import` hook
- Fixes mixed English/French display caused by WooCommerce reverting normalized content

### 3.8.1
- **FIX:** Variation description translations now always normalized
- **FIX:** post_excerpt normalization now also updates the DB

### 3.8.0
- Bulletproof content flattening: handles ALL content formats
- Strips `<p>`, `<div>`, Gutenberg blocks, `<br>` variants, `&nbsp;` spacers

### 3.7.0
- Auto-normalize paragraph breaks (replaces manual `_trp_convert_linebreaks` field)

### 3.5.0
- **FIX:** Handle WordPress `wptexturize()` smart quotes in translation matching

### 3.4.0
- Keep variation description translation meta for direct theme lookup

### 3.3.0
- **FIX:** Attribute translation for variations now correctly matches HTML entities

### 3.1.0
- **NEW:** Variation description translation support (`_trp_variation_desc_[lang]`)

### 3.0.0
- **NEW:** Full support for WooCommerce variable products and variations
- Force update mode: existing translations are always overwritten

### 2.3.0
- **NEW:** WooCommerce category and attribute translation support

### 2.0.0
- Rewrote to use official TranslatePress Custom API

### 1.0.0
- Initial release

---

## Support

For issues or questions:
- **Email:** oli@olivierbigras.com
- **Website:** [olivierbigras.com](https://olivierbigras.com)
- **GitHub:** [github.com/olivierbigras/oli-import-sync-for-translatepress](https://github.com/olivierbigras/oli-import-sync-for-translatepress)
