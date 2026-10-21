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

/**
 * Read an option from the raw store, bypassing option_{$name} filters.
 *
 * WP-CLI --skip-plugins (and similar) filter get_option( 'active_plugins' ).
 * Rewriting that filtered list would persist dropped plugins.
 *
 * @param string $option Option name.
 * @return mixed|null Unserialized value, or null if not found.
 */
function oli_import_sync_trp_get_raw_option( $option ) {
	if ( function_exists( 'wp_load_alloptions' ) ) {
		$alloptions = wp_load_alloptions();
		if ( is_array( $alloptions ) && array_key_exists( $option, $alloptions ) ) {
			return $alloptions[ $option ];
		}
	}

	global $wpdb;
	if ( isset( $wpdb ) && is_object( $wpdb ) && ! empty( $wpdb->options ) ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- raw value, skip option_* filters (e.g. wp --skip-plugins).
		$raw = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT option_value FROM {$wpdb->options} WHERE option_name = %s LIMIT 1",
				$option
			)
		);
		if ( is_string( $raw ) ) {
			return maybe_unserialize( $raw );
		}
	}

	return null;
}

/**
 * Read a network option from sitemeta without site_option_* filters.
 *
 * @param string $option Option name.
 * @return mixed|null Unserialized value, or null if not found.
 */
function oli_import_sync_trp_get_raw_site_option( $option ) {
	global $wpdb;
	if ( ! isset( $wpdb ) || ! is_object( $wpdb ) || empty( $wpdb->sitemeta ) ) {
		return null;
	}

	$network_id = function_exists( 'get_current_network_id' ) ? get_current_network_id() : ( isset( $wpdb->siteid ) ? (int) $wpdb->siteid : 1 );

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- raw network value, skip site_option_* filters.
	$raw = $wpdb->get_var(
		$wpdb->prepare(
			'SELECT meta_value FROM ' . $wpdb->sitemeta . ' WHERE meta_key = %s AND site_id = %d LIMIT 1',
			$option,
			$network_id
		)
	);

	if ( is_string( $raw ) ) {
		return maybe_unserialize( $raw );
	}

	return null;
}

$oli_import_sync_trp_old_file = 'wpai-translatepress-sync.php';
$oli_import_sync_trp_new_file = 'oli-import-sync-for-translatepress.php';
$oli_import_sync_trp_dir      = basename( __DIR__ );
$oli_import_sync_trp_old      = $oli_import_sync_trp_dir . '/' . $oli_import_sync_trp_old_file;
$oli_import_sync_trp_new      = $oli_import_sync_trp_dir . '/' . $oli_import_sync_trp_new_file;

$oli_import_sync_trp_active = oli_import_sync_trp_get_raw_option( 'active_plugins' );
if ( is_array( $oli_import_sync_trp_active ) ) {
	$oli_import_sync_trp_changed = false;
	$oli_import_sync_trp_had_new = in_array( $oli_import_sync_trp_new, $oli_import_sync_trp_active, true );

	foreach ( $oli_import_sync_trp_active as $oli_import_sync_trp_i => $oli_import_sync_trp_plugin ) {
		if ( $oli_import_sync_trp_plugin === $oli_import_sync_trp_old ) {
			$oli_import_sync_trp_active[ $oli_import_sync_trp_i ] = $oli_import_sync_trp_new;
			$oli_import_sync_trp_changed                         = true;
		}
	}

	if ( $oli_import_sync_trp_changed && function_exists( 'update_option' ) ) {
		if ( $oli_import_sync_trp_had_new ) {
			$oli_import_sync_trp_active = array_values( array_unique( $oli_import_sync_trp_active ) );
		}
		update_option( 'active_plugins', $oli_import_sync_trp_active );
	}
}

if ( function_exists( 'is_multisite' ) && is_multisite() ) {
	$oli_import_sync_trp_sitewide = oli_import_sync_trp_get_raw_site_option( 'active_sitewide_plugins' );
	if ( is_array( $oli_import_sync_trp_sitewide ) && isset( $oli_import_sync_trp_sitewide[ $oli_import_sync_trp_old ] ) ) {
		$oli_import_sync_trp_sitewide[ $oli_import_sync_trp_new ] = $oli_import_sync_trp_sitewide[ $oli_import_sync_trp_old ];
		unset( $oli_import_sync_trp_sitewide[ $oli_import_sync_trp_old ] );
		if ( function_exists( 'update_site_option' ) ) {
			update_site_option( 'active_sitewide_plugins', $oli_import_sync_trp_sitewide );
		}
	}
}

require_once __DIR__ . '/' . $oli_import_sync_trp_new_file;
