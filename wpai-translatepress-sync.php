<?php
/**
 * Legacy bootstrap for installs still activated as
 * {dir}/wpai-translatepress-sync.php.
 *
 * This file intentionally has no Plugin Name header so WordPress lists only
 * oli-import-sync-for-translatepress.php. If this path is already in
 * active_plugins, the plugin still loads after an in-place file replacement,
 * then the active path is rewritten to the slug-matching file.
 *
 * @package Oli_Import_Sync_For_TranslatePress
 */

defined( 'ABSPATH' ) || exit;

$oli_import_sync_trp_old_file = 'wpai-translatepress-sync.php';
$oli_import_sync_trp_new_file = 'oli-import-sync-for-translatepress.php';
$oli_import_sync_trp_dir      = basename( __DIR__ );

if ( function_exists( 'get_option' ) ) {
	$oli_import_sync_trp_active  = (array) get_option( 'active_plugins', array() );
	$oli_import_sync_trp_changed = false;
	$oli_import_sync_trp_old     = $oli_import_sync_trp_dir . '/' . $oli_import_sync_trp_old_file;
	$oli_import_sync_trp_new     = $oli_import_sync_trp_dir . '/' . $oli_import_sync_trp_new_file;

	foreach ( $oli_import_sync_trp_active as $oli_import_sync_trp_i => $oli_import_sync_trp_plugin ) {
		if ( $oli_import_sync_trp_plugin === $oli_import_sync_trp_old ) {
			$oli_import_sync_trp_active[ $oli_import_sync_trp_i ] = $oli_import_sync_trp_new;
			$oli_import_sync_trp_changed                         = true;
		}
	}

	if ( $oli_import_sync_trp_changed ) {
		update_option( 'active_plugins', array_values( array_unique( $oli_import_sync_trp_active ) ) );
	}
}

if ( function_exists( 'is_multisite' ) && is_multisite() && function_exists( 'get_site_option' ) ) {
	$oli_import_sync_trp_sitewide = (array) get_site_option( 'active_sitewide_plugins', array() );
	$oli_import_sync_trp_old      = $oli_import_sync_trp_dir . '/' . $oli_import_sync_trp_old_file;
	$oli_import_sync_trp_new      = $oli_import_sync_trp_dir . '/' . $oli_import_sync_trp_new_file;

	if ( isset( $oli_import_sync_trp_sitewide[ $oli_import_sync_trp_old ] ) ) {
		$oli_import_sync_trp_sitewide[ $oli_import_sync_trp_new ] = $oli_import_sync_trp_sitewide[ $oli_import_sync_trp_old ];
		unset( $oli_import_sync_trp_sitewide[ $oli_import_sync_trp_old ] );
		update_site_option( 'active_sitewide_plugins', $oli_import_sync_trp_sitewide );
	}
}

require_once __DIR__ . '/' . $oli_import_sync_trp_new_file;
