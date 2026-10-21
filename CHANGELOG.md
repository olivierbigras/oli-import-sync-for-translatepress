# Changelog

All notable changes to Oli Import Sync for TranslatePress are documented here.

## 3.17.0 – 2026-10-21

Prepared 3.16.2 for wordpress.org (main file = slug, escaped admin output, prefixed options).

### Compatibility

- Main plugin file is now `oli-import-sync-for-translatepress.php`.
- `wpai-translatepress-sync.php` remains as a **header-less** bootstrap: if an existing site still has `{dir}/wpai-translatepress-sync.php` in `active_plugins` (or network `active_sitewide_plugins`), the plugin still loads and that path is rewritten to the new file. WordPress lists only one plugin.
- Settings bookmarks for `page=wpai-trp-sync` redirect to `page=oli-import-sync-trp`.

### Options / prefix

- Copies `wpai_trp_logging_enabled` → `oli_import_sync_trp_logging_enabled` and `wpai_trp_auto_slug_enabled` → `oli_import_sync_trp_auto_slug_enabled`, then deletes the old keys.
- Class: `Oli_Import_Sync_For_TranslatePress`.
- Helpers: `oli_import_sync_trp_add_translation()` and `oli_import_sync_trp_bulk_add_translations()`. Legacy `wpai_trp_*` names still work.

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
