<?php
/**
 * Uninstall Oli Import Sync for TranslatePress.
 *
 * @package Oli_Import_Sync_For_TranslatePress
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$oli_import_sync_trp_log_basename = get_option( 'oli_import_sync_trp_log_basename', '' );

$oli_import_sync_trp_options = array(
	'oli_import_sync_trp_logging_enabled',
	'oli_import_sync_trp_auto_slug_enabled',
	'oli_import_sync_trp_schema',
	'oli_import_sync_trp_log_basename',
	'wpai_trp_logging_enabled',
	'wpai_trp_auto_slug_enabled',
);

foreach ( $oli_import_sync_trp_options as $oli_import_sync_trp_option ) {
	delete_option( $oli_import_sync_trp_option );
	delete_site_option( $oli_import_sync_trp_option );
}

delete_metadata( 'user', 0, 'oli_import_sync_trp_donate_dismissed', '', true );

$oli_import_sync_trp_legacy_log = plugin_dir_path( __FILE__ ) . 'sync-log.txt';
if ( file_exists( $oli_import_sync_trp_legacy_log ) ) {
	wp_delete_file( $oli_import_sync_trp_legacy_log );
}

if ( function_exists( 'wp_upload_dir' ) ) {
	$oli_import_sync_trp_uploads = wp_upload_dir( null, false );
	if ( is_array( $oli_import_sync_trp_uploads ) && empty( $oli_import_sync_trp_uploads['error'] ) && ! empty( $oli_import_sync_trp_uploads['basedir'] ) ) {
		$oli_import_sync_trp_dir = trailingslashit( $oli_import_sync_trp_uploads['basedir'] ) . 'oli-import-sync';
		if ( is_string( $oli_import_sync_trp_log_basename ) && preg_match( '/^sync-[A-Za-z0-9]{12}\.log$/', $oli_import_sync_trp_log_basename ) ) {
			wp_delete_file( $oli_import_sync_trp_dir . '/' . $oli_import_sync_trp_log_basename );
		}
		wp_delete_file( $oli_import_sync_trp_dir . '/index.php' );
		wp_delete_file( $oli_import_sync_trp_dir . '/.htaccess' );
		if ( is_dir( $oli_import_sync_trp_dir ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- empty log dir after deleting known files.
			@rmdir( $oli_import_sync_trp_dir );
		}
	}
}
