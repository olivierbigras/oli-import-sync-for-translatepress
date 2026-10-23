# Changelog

All notable changes to Oli Import Sync for TranslatePress are documented here.

## 3.17.2 – 2026-10-23

In-place update fix and SQL identifier hardening.

- Restored the 3.16.2 main file name (`wpai-translatepress-sync.php`) with the plugin header so WordPress keeps the plugin active after a ZIP update from 3.16.2. `oli-import-sync-for-translatepress.php` is a header-less loader only.
- Dictionary and slug queries use `%i` identifiers on WordPress 6.2+; older WordPress interpolates only `$wpdb->prefix` tables that pass a character-class check. No user input in table names.
- Generated `languages/oli-import-sync-for-translatepress.pot`.
- Request data (`$_GET` / `$_POST`) is unslashed and sanitized; clear-log and donate AJAX still require `manage_options` and a nonce.

## 3.17.1 – 2026-10-21

WordPress.org / Plugin Check follow-up on 3.17.0.

- Translators comment on the Field Reference `sprintf( __( … %s … ) )`. The other two `sprintf( __( … ) )` calls already had one.
- Removed unprefixed legacy helpers `wpai_trp_add_translation()` and `wpai_trp_bulk_add_translations()`. Use `oli_import_sync_trp_*`.
- Logging writes only when plugin logging is enabled or `WP_DEBUG_LOG` is active (no unconditional `error_log()`).
- Compatibility shim reads raw `active_plugins` / `active_sitewide_plugins` (no `option_*` filters) and rewrites only this plugin's path, so `wp --skip-plugins` cannot persist a filtered list.
- `.distignore` and `.gitattributes` `export-ignore` drop tests, tooling, internal markdown, and other dev files from the ZIP. `readme.txt` is kept.
- Plugin URI remains https://github.com/olivierbigras/oli-import-sync-for-translatepress

## 3.17.0 – 2026-10-21

Prepared 3.16.2 for wordpress.org (main file = slug, escaped admin output, prefixed options).

### Compatibility

- Main plugin file is now `oli-import-sync-for-translatepress.php`.
- `wpai-translatepress-sync.php` remains as a **header-less** bootstrap: if an existing site still has `{dir}/wpai-translatepress-sync.php` in `active_plugins` (or network `active_sitewide_plugins`), the plugin still loads and that path is rewritten to the new file. WordPress lists only one plugin.
- Settings bookmarks for `page=wpai-trp-sync` redirect to `page=oli-import-sync-trp`.

### Options / prefix

- Copies `wpai_trp_logging_enabled` → `oli_import_sync_trp_logging_enabled` and `wpai_trp_auto_slug_enabled` → `oli_import_sync_trp_auto_slug_enabled`, then deletes the old keys.
- Class: `Oli_Import_Sync_For_TranslatePress`.
- Helpers: `oli_import_sync_trp_add_translation()` and `oli_import_sync_trp_bulk_add_translations()`.

### WordPress.org / coding

- Admin CSS/JS are real files (`admin/css/admin.css`, `admin/js/admin.js`) enqueued with `wp_enqueue_*`. Config goes through `wp_localize_script`. No inline `<script>`, `<style>`, or `onclick`.
- Dashboard status cells use `esc_html` / `wp_kses` (the six previously unescaped echoes).
- Clear-log requires `manage_options` + nonce.
- Plugin URI: https://github.com/olivierbigras/oli-import-sync-for-translatepress
- `readme.txt` External services section (PayPal donate links; no background remote calls).
- Tested up to WordPress 7.1.
- `LICENSE` (GPL-2.0-or-later) and `uninstall.php` (removes new and legacy options, donate user meta, and the log file).

## 3.16.2

- Settings and Donate links on the Plugins screen, optional support notice, footer donation link (PayPal).

## 3.16.0

- Mirror WordPress's `-2`, `-3` slug auto-disambiguator from the EN slug onto auto-derived translated slugs.

## 3.15.0

- Post / product slug translation via `_trp_slug_[lang]` and auto-derive from translated title.

## 3.14.0

- Renamed plugin to "Oli Import Sync for TranslatePress" (trademark-friendly).
- Text domain: `oli-import-sync-for-translatepress`.
