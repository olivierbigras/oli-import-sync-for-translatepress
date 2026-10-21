<?php
/**
 * Uninstall Oli Import Sync for TranslatePress.
 *
 * @package Oli_Import_Sync_For_TranslatePress
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$oli_import_sync_trp_options = array(
	'oli_import_sync_trp_logging_enabled',
	'oli_import_sync_trp_auto_slug_enabled',
	'oli_import_sync_trp_schema',
	'wpai_trp_logging_enabled',
	'wpai_trp_auto_slug_enabled',
);

foreach ( $oli_import_sync_trp_options as $oli_import_sync_trp_option ) {
	delete_option( $oli_import_sync_trp_option );
	delete_site_option( $oli_import_sync_trp_option );
}

delete_metadata( 'user', 0, 'oli_import_sync_trp_donate_dismissed', '', true );

$oli_import_sync_trp_log = plugin_dir_path( __FILE__ ) . 'sync-log.txt';
if ( file_exists( $oli_import_sync_trp_log ) ) {
	wp_delete_file( $oli_import_sync_trp_log );
}
