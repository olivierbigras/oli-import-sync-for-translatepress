<?php
/**
 * Header-less loader for sites that activated 3.17.0–3.17.1 as
 * {dir}/oli-import-sync-for-translatepress.php.
 *
 * This file has no Plugin Name header so WordPress lists only
 * wpai-translatepress-sync.php. Loading it remaps raw active_plugins and
 * active_sitewide_plugins (maybe_unserialize) from this basename to
 * wpai-translatepress-sync.php, idempotently, before validate_active_plugins()
 * rejects a header-less path.
 *
 * @package Oli_Import_Sync_For_TranslatePress
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/wpai-translatepress-sync.php';
