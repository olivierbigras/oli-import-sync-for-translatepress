<?php
/**
 * Header-less loader for sites that activated 3.17.0–3.17.1 as
 * {dir}/oli-import-sync-for-translatepress.php.
 *
 * The listed plugin file is wpai-translatepress-sync.php (same basename as 3.16.2)
 * so an in-place update from 3.16.2 stays active. This file has no Plugin Name
 * header so WordPress lists only one plugin.
 *
 * @package Oli_Import_Sync_For_TranslatePress
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/wpai-translatepress-sync.php';
