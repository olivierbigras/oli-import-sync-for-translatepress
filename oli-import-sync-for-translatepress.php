<?php
/**
 * Plugin Name: Oli Import Sync for TranslatePress
 * Plugin URI: https://github.com/olivierbigras/oli-import-sync-for-translatepress
 * Description: Sync translations from WP All Import into TranslatePress via the official Custom API. Map _trp_title_[lang] and _trp_content_[lang] custom fields in your import. Not affiliated with TranslatePress.
 * Version: 3.17.1
 * Author: Olivier Bigras
 * Author URI: https://olivierbigras.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: oli-import-sync-for-translatepress
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.4
 *
 * @package Oli_Import_Sync_For_TranslatePress
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( defined( 'OLI_IMPORT_SYNC_TRP_LOADED' ) ) {
    return;
}
define( 'OLI_IMPORT_SYNC_TRP_LOADED', true );

define( 'OLI_IMPORT_SYNC_TRP_VERSION', '3.17.1' );
define( 'OLI_IMPORT_SYNC_TRP_FILE', __FILE__ );
define( 'OLI_IMPORT_SYNC_TRP_DIR', plugin_dir_path( __FILE__ ) );
define( 'OLI_IMPORT_SYNC_TRP_DONATE_URL', 'https://www.paypal.com/donate/?hosted_button_id=29SAPKFAHMTNW' );

/**
 * Copy legacy wpai_trp_* options to oli_import_sync_trp_* then delete the old keys.
 *
 * @return void
 */
function oli_import_sync_trp_migrate_legacy_options() {
    if ( '3.17.0' === get_option( 'oli_import_sync_trp_schema', '' ) ) {
        return;
    }

    $map = array(
        'wpai_trp_logging_enabled'   => 'oli_import_sync_trp_logging_enabled',
        'wpai_trp_auto_slug_enabled' => 'oli_import_sync_trp_auto_slug_enabled',
    );

    foreach ( $map as $old => $new ) {
        if ( false !== get_option( $new, false ) ) {
            delete_option( $old );
            continue;
        }

        $old_value = get_option( $old, null );
        if ( null === $old_value || false === $old_value ) {
            continue;
        }

        add_option( $new, $old_value );
        delete_option( $old );
    }

    update_option( 'oli_import_sync_trp_schema', '3.17.0' );
}

/**
 * =============================================================================
 * WP ALL IMPORT + TRANSLATEPRESS SYNC PLUGIN (v3.17.1 - Using Official API)
 * =============================================================================
 * 
 * REQUIRES: TranslatePress Custom API plugin
 * 
 * NOTE: All paragraph separators (<p>, <br>, newlines) are automatically stripped
 * from imported content. Both original and translated text become a single text
 * block so TranslatePress always detects ONE string = ONE dictionary entry.
 * 
 * HOW TO USE IN WP ALL IMPORT:
 * 
 * 1. In your import template, map these custom fields:
 *    - _trp_title_[lang]    → Your translated title field from CSV/XML
 *    - _trp_content_[lang]  → Your translated content/description field
 *    - _trp_excerpt_[lang]  → Your translated excerpt/short description (optional)
 * 
 *    Where [lang] is the language code (e.g., fr_CA, es_ES, de_DE)
 *    Examples: _trp_title_fr_CA, _trp_content_fr_CA
 * 
 * FOR WOOCOMMERCE PRODUCTS (Categories, Attributes & Variations):
 *    - _trp_cat_[lang]              → Translated category names (pipe-separated for multiple)
 *    - _trp_cat_slug_[lang]         → Translated category slugs (requires SEO Pack)
 *    - _trp_attr_[slug]_[lang]      → Translated attribute values (e.g., _trp_attr_color_fr_CA)
 *    - _trp_variation_desc_[lang]   → Translated variation description
 *    
 *    Examples: _trp_cat_fr_CA = "Chaises|Tables", _trp_attr_color_fr_CA = "Rouge|Bleu"
 * 
 * 2. Map your default language fields normally:
 *    - Title   → Default language title
 *    - Content → Default language description
 *    - Excerpt → Default language short description
 * 
 * 3. Run the import - translations are automatically synced to TranslatePress
 */

class Oli_Import_Sync_For_TranslatePress {

    /**
     * Custom field prefixes for post fields
     */
    private $field_prefixes = array(
        '_trp_title_'   => 'post_title',
        '_trp_content_' => 'post_content',
        '_trp_excerpt_' => 'post_excerpt',
    );

    /**
     * Prefixes for WooCommerce category translations
     */
    private $cat_prefix = '_trp_cat_';
    private $cat_slug_prefix = '_trp_cat_slug_';

    /**
     * Prefix for attribute translations
     */
    private $attr_prefix = '_trp_attr_';

    /**
     * Prefix for variation description translations
     */
    private $variation_desc_prefix = '_trp_variation_desc_';

    /**
     * Prefix for translated post/product slug overrides
     * If a value is provided here it takes priority over auto-deriving from the title.
     */
    private $slug_prefix = '_trp_slug_';

    /**
     * Path to the plugin's own log file
     */
    private $log_file;

    /**
     * Constructor
     */
    public function __construct() {
        $this->log_file = OLI_IMPORT_SYNC_TRP_DIR . 'sync-log.txt';

        oli_import_sync_trp_migrate_legacy_options();

        // Flatten content to single block before WP All Import saves
        add_filter( 'pmxi_article_data', array( $this, 'convert_linebreaks_before_save' ), 10, 4 );

        // Hook into WP All Import after post is saved
        add_action( 'pmxi_saved_post', array( $this, 'sync_translations' ), 10, 3 );

        // Safety net: re-flatten after entire import record (catches WooCommerce reverts)
        add_action( 'pmxi_after_post_import', array( $this, 'normalize_content_after_import' ), 10, 1 );
        
        // Admin notice for configuration help
        add_action( 'admin_notices', array( $this, 'admin_notice' ) );

        // Settings page
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        add_action( 'admin_init', array( $this, 'handle_clear_log' ) );
        add_action( 'admin_init', array( $this, 'redirect_legacy_settings_page' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ), 10, 1 );
        add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );
        foreach ( $this->plugin_basenames() as $basename ) {
            add_filter( 'plugin_action_links_' . $basename, array( $this, 'plugin_action_links' ) );
        }
        add_filter( 'plugin_row_meta', array( $this, 'plugin_row_meta' ), 10, 2 );
        add_action( 'admin_notices', array( $this, 'donate_notice' ) );
        add_action( 'wp_ajax_oli_import_sync_trp_dismiss_donate', array( $this, 'ajax_dismiss_donate' ) );
    }

    /**
     * Current and legacy plugin basenames (folder/file.php).
     *
     * @return string[]
     */
    private function plugin_basenames() {
        $current = plugin_basename( OLI_IMPORT_SYNC_TRP_FILE );
        $legacy  = dirname( $current ) . '/wpai-translatepress-sync.php';
        return array_unique( array( $current, $legacy ) );
    }

    /**
     * Settings + Donate on the Plugins screen.
     *
     * @param string[] $links Links.
     * @return string[]
     */
    public function plugin_action_links( $links ) {
        $settings = '<a href="' . esc_url( admin_url( 'options-general.php?page=oli-import-sync-trp' ) ) . '">' . esc_html__( 'Settings', 'oli-import-sync-for-translatepress' ) . '</a>';
        $donate   = '<a href="' . esc_url( OLI_IMPORT_SYNC_TRP_DONATE_URL ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Donate', 'oli-import-sync-for-translatepress' ) . '</a>';
        array_unshift( $links, $settings, $donate );
        return $links;
    }

    /**
     * Donate in plugin meta row.
     *
     * @param string[] $links Links.
     * @param string   $file  Basename.
     * @return string[]
     */
    public function plugin_row_meta( $links, $file ) {
        if ( ! in_array( $file, $this->plugin_basenames(), true ) ) {
            return $links;
        }
        $links[] = '<a href="' . esc_url( OLI_IMPORT_SYNC_TRP_DONATE_URL ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Donate', 'oli-import-sync-for-translatepress' ) . '</a>';
        return $links;
    }

    /**
     * Dismissible support notice on the settings screen.
     */
    public function donate_notice() {
        $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
        if ( ! $screen || 'settings_page_oli-import-sync-trp' !== $screen->id ) {
            return;
        }
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        if ( get_user_meta( get_current_user_id(), 'oli_import_sync_trp_donate_dismissed', true ) ) {
            return;
        }
        ?>
        <div class="notice notice-info is-dismissible oli-import-sync-trp-donate-notice">
            <p>
                <?php
                echo wp_kses(
                    sprintf(
                        /* translators: %s: donate URL */
                        __( 'Enjoying Oli Import Sync for TranslatePress? <a href="%s" target="_blank" rel="noopener noreferrer">Buy me a coffee</a> — your support keeps the plugin free and maintained.', 'oli-import-sync-for-translatepress' ),
                        esc_url( OLI_IMPORT_SYNC_TRP_DONATE_URL )
                    ),
                    array(
                        'a' => array(
                            'href'   => true,
                            'target' => true,
                            'rel'    => true,
                        ),
                    )
                );
                ?>
            </p>
        </div>
        <?php
    }

    /**
     * Persist donate notice dismissal.
     */
    public function ajax_dismiss_donate() {
        check_ajax_referer( 'oli_import_sync_trp_donate', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( null, 403 );
        }
        update_user_meta( get_current_user_id(), 'oli_import_sync_trp_donate_dismissed', 1 );
        wp_send_json_success();
    }

    /**
     * Load plugin text domain for translations.
     */
    public function load_textdomain() {
        load_plugin_textdomain(
            'oli-import-sync-for-translatepress',
            false,
            dirname( plugin_basename( OLI_IMPORT_SYNC_TRP_FILE ) ) . '/languages'
        );
    }

    /**
     * Enqueue admin CSS/JS for the plugin settings screen (WordPress.org: no raw style/script tags in templates).
     *
     * @param string $hook_suffix Current admin page hook.
     */
    public function enqueue_admin_assets( $hook_suffix ) {
        if ( $hook_suffix !== 'settings_page_oli-import-sync-trp' ) {
            return;
        }

        wp_enqueue_style(
            'oli-import-sync-trp-admin',
            plugins_url( 'admin/css/admin.css', OLI_IMPORT_SYNC_TRP_FILE ),
            array(),
            OLI_IMPORT_SYNC_TRP_VERSION
        );

        wp_enqueue_script(
            'oli-import-sync-trp-admin',
            plugins_url( 'admin/js/admin.js', OLI_IMPORT_SYNC_TRP_FILE ),
            array(),
            OLI_IMPORT_SYNC_TRP_VERSION,
            true
        );

        wp_localize_script(
            'oli-import-sync-trp-admin',
            'oliImportSyncTrp',
            array(
                'ajaxUrl'         => admin_url( 'admin-ajax.php' ),
                'donateNonce'     => wp_create_nonce( 'oli_import_sync_trp_donate' ),
                'clearLogConfirm' => __( 'Clear the entire log?', 'oli-import-sync-for-translatepress' ),
                'copied'          => __( 'Copied!', 'oli-import-sync-for-translatepress' ),
            )
        );
    }

    /**
     * Flatten content to a single text block before WordPress saves the post
     * Strips all paragraph separators so wpautop() wraps in ONE <p> tag
     * 
     * @since 3.13.0 Strips to spaces instead of converting to <br>
     * @param array $article_data Post data being imported
     * @param object $import Import object
     * @param int $post_id Post ID (0 for new posts)
     * @param int $current_position Current import position
     * @return array Modified article data
     */
    public function convert_linebreaks_before_save( $article_data, $import, $post_id, $current_position ) {
        try {
            if ( ! is_array( $article_data ) ) {
                return $article_data;
            }
            
            $title = isset( $article_data['post_title'] ) ? $article_data['post_title'] : '(unknown)';
            
            if ( ! empty( $article_data['post_content'] ) && is_string( $article_data['post_content'] ) ) {
                $before = $article_data['post_content'];
                $article_data['post_content'] = $this->convert_linebreaks( $article_data['post_content'] );
                if ( $before !== $article_data['post_content'] ) {
                    $this->log( sprintf( 'convert_linebreaks_before_save: Normalized post_content for "%s" (post #%d)', $title, $post_id ) );
                }
            } else {
                $this->log( sprintf( 'convert_linebreaks_before_save: post_content is EMPTY for "%s" (post #%d) - content not in import data', $title, $post_id ) );
            }
            
            if ( ! empty( $article_data['post_excerpt'] ) && is_string( $article_data['post_excerpt'] ) ) {
                $article_data['post_excerpt'] = $this->convert_linebreaks( $article_data['post_excerpt'] );
            }
        } catch ( \Throwable $e ) {
            $this->log( 'Error in convert_linebreaks_before_save: ' . $e->getMessage() );
        }
        
        return $article_data;
    }

    /**
     * Log messages for debugging.
     * Writes only when plugin logging is enabled and/or WP_DEBUG_LOG is active.
     */
    private function log( $message ) {
        $plugin_logging = (bool) get_option( 'oli_import_sync_trp_logging_enabled', 0 );
        $wp_debug_log   = defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG;

        if ( ! $plugin_logging && ! $wp_debug_log ) {
            return;
        }

        if ( $plugin_logging && is_string( $this->log_file ) && '' !== $this->log_file ) {
            $line = '[' . current_time( 'Y-m-d H:i:s' ) . '] ' . $message . "\n";
            file_put_contents( $this->log_file, $line, FILE_APPEND | LOCK_EX );
        }

        if ( $wp_debug_log ) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- only when WP_DEBUG_LOG is active.
            error_log( '[Oli-Import-Sync-TRP] ' . $message );
        }
    }

    /**
     * Force insert/update a translation by deleting existing one first
     * This ensures translations are ALWAYS updated, not skipped if they exist
     * 
     * Also inserts HTML-encoded version if the string contains special characters,
     * because TranslatePress may detect the string with HTML entities on the frontend.
     * 
     * @param string $original    Original string
     * @param string $translated  Translated string
     * @param string $lang_code   Language code (e.g., 'fr_CA')
     * @return array|WP_Error Result from trpc_insert_translation
     */
    private function force_insert_translation( $original, $translated, $lang_code ) {
        global $wpdb;
        
        // First, delete any existing translation for this original string
        $trp = TRP_Translate_Press::get_trp_instance();
        $trp_settings_obj = $trp->get_component( 'settings' );
        $trp_settings = $trp_settings_obj->get_settings();
        
        // Get the table name for this language (format: trp_dictionary_[default]_[translation])
        $default_lang = strtolower( $trp_settings['default-language'] );
        $table_name = $wpdb->prefix . 'trp_dictionary_' . $default_lang . '_' . strtolower( $lang_code );
        
        // Delete existing translation for this original string
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM `$table_name` WHERE original = %s",
                $original
            )
        );
        
        // Also check for HTML-encoded version and delete it too
        $original_encoded = htmlspecialchars( $original, ENT_QUOTES, 'UTF-8' );
        if ( $original_encoded !== $original ) {
            $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM `$table_name` WHERE original = %s",
                    $original_encoded
                )
            );
        }
        
        // Now insert the new translation using the API
        if ( function_exists( 'trpc_insert_translation' ) ) {
            // Insert the raw version
            $result = trpc_insert_translation( $original, $translated, $lang_code, array( 'status' => 2 ) );
            
            // Also insert HTML-encoded version if different (for frontend matching)
            // TranslatePress detects strings after esc_html() is applied, so quotes become &quot;
            if ( $original_encoded !== $original ) {
                $translated_encoded = htmlspecialchars( $translated, ENT_QUOTES, 'UTF-8' );
                trpc_insert_translation( $original_encoded, $translated_encoded, $lang_code, array( 'status' => 2 ) );
            }
            
            // Handle wptexturize'd version (WordPress converts straight quotes to smart quotes on render)
            // TranslatePress detects the rendered page where " becomes &#8221; / &#8220; etc.
            $original_texturized = wptexturize( $original );
            if ( $original_texturized !== $original ) {
                $translated_texturized = wptexturize( $translated );
                
                // Update any existing auto-detected entries that match the texturized version
                $wpdb->update(
                    $table_name,
                    array(
                        'translated' => $translated_texturized,
                        'status'     => 2,
                    ),
                    array( 'original' => $original_texturized ),
                    array( '%s', '%d' ),
                    array( '%s' )
                );
                
                // If no rows were updated, insert a new entry
                if ( $wpdb->rows_affected === 0 ) {
                    trpc_insert_translation( $original_texturized, $translated_texturized, $lang_code, array( 'status' => 2 ) );
                }
                
                // Also handle the HTML-encoded texturized version
                $original_tex_encoded = htmlspecialchars( $original_texturized, ENT_QUOTES, 'UTF-8' );
                if ( $original_tex_encoded !== $original_texturized ) {
                    $translated_tex_encoded = htmlspecialchars( $translated_texturized, ENT_QUOTES, 'UTF-8' );
                    
                    $wpdb->update(
                        $table_name,
                        array(
                            'translated' => $translated_tex_encoded,
                            'status'     => 2,
                        ),
                        array( 'original' => $original_tex_encoded ),
                        array( '%s', '%d' ),
                        array( '%s' )
                    );
                    
                    if ( $wpdb->rows_affected === 0 ) {
                        trpc_insert_translation( $original_tex_encoded, $translated_tex_encoded, $lang_code, array( 'status' => 2 ) );
                    }
                }
            }
            
            return $result;
        }
        
        return new WP_Error( 'api_not_available', 'TranslatePress Custom API not available' );
    }

    /**
     * Flatten content into a single text block for TranslatePress compatibility
     * 
     * TranslatePress detects each block-level HTML element (<p>, <div>) as a
     * separate translatable string. This function strips ALL paragraph separators
     * and joins everything with spaces, so wpautop() wraps it in ONE <p> tag
     * and TranslatePress always detects ONE string = ONE dictionary entry.
     * 
     * Preserves inline HTML (<strong>, <em>, <a>, <span>, etc.).
     * 
     * @since 3.13.0 Replaced <br> conversion with space-joining
     * @param string $content Content in any format
     * @return string Single-line text block (no paragraph separators)
     */
    private function convert_linebreaks( $content ) {
        if ( empty( $content ) || ! is_string( $content ) ) {
            return $content;
        }

        // Strip Gutenberg block comments
        $content = preg_replace( '/<!--\s*\/?\s*wp:\w+[^>]*?-->/s', '', $content );

        // Replace block-level closing/opening pairs with a space
        $content = preg_replace( '/<\/(p|div)>\s*<(p|div)[^>]*>/i', ' ', $content );

        // Strip remaining <p>, </p>, <div>, </div> tags
        $content = preg_replace( '/<\/?(p|div)[^>]*>/i', ' ', $content );

        // Replace all <br> variants with a space
        $content = preg_replace( '/<br\s*\/?>/i', ' ', $content );

        // Replace all newlines with a space
        $content = preg_replace( '/[\r\n]+/', ' ', $content );

        // Replace &nbsp; with a space
        $content = str_replace( '&nbsp;', ' ', $content );

        // Collapse multiple spaces into one
        $content = preg_replace( '/\s{2,}/', ' ', $content );

        return trim( $content );
    }

    /**
     * Get configured translation languages from TranslatePress
     */
    private function get_translation_languages() {
        if ( function_exists( 'trpc_get_languages' ) ) {
            $langs = trpc_get_languages();
            return isset( $langs['translations'] ) ? $langs['translations'] : array();
        }
        
        // Fallback to settings
        $trp_settings = get_option( 'trp_settings', array() );
        $all_langs = isset( $trp_settings['translation-languages'] ) ? $trp_settings['translation-languages'] : array();
        $default = isset( $trp_settings['default-language'] ) ? $trp_settings['default-language'] : 'en_US';
        
        return array_diff( $all_langs, array( $default ) );
    }

    /**
     * Sync translations after WP All Import saves a post
     */
    public function sync_translations( $post_id, $xml_node, $is_update ) {
        $this->log( '=== SYNC START for post #' . $post_id . ' ===' );
        
        try {
            // Check if TranslatePress Custom API is available
            if ( ! function_exists( 'trpc_insert_translation' ) ) {
                $this->log( 'ERROR: trpc_insert_translation function not available!' );
                return;
            }
            $this->log( 'trpc_insert_translation function is available' );

            $post = get_post( $post_id );
            if ( ! $post ) {
                $this->log( 'ERROR: Could not get post #' . $post_id );
                return;
            }
            $this->log( 'Post type: ' . $post->post_type );

            $translation_languages = $this->get_translation_languages();
            if ( empty( $translation_languages ) ) {
                $this->log( 'No translation languages configured' );
                return;
            }
            $this->log( 'Translation languages: ' . implode( ', ', $translation_languages ) );

            $translations_added = 0;

            // Get all post meta to find our translation fields
            $all_meta = get_post_meta( $post_id );
            $this->log( 'Found ' . count( $all_meta ) . ' meta fields' );
            
            // Log which _trp_ fields exist
            $trp_fields = array_filter( array_keys( $all_meta ), function( $key ) {
                return strpos( $key, '_trp_' ) === 0;
            });
            $this->log( 'TRP fields found: ' . ( empty( $trp_fields ) ? 'NONE' : implode( ', ', $trp_fields ) ) );

            foreach ( $this->field_prefixes as $prefix => $post_field ) {
                foreach ( $translation_languages as $lang_code ) {
                    $meta_key = $prefix . $lang_code;
                    
                    // Check if this meta exists
                    if ( ! isset( $all_meta[ $meta_key ] ) ) {
                        continue;
                    }
                    
                    $translated_value = $all_meta[ $meta_key ][0];
                    
                    if ( empty( $translated_value ) ) {
                        continue;
                    }

                    // Get original value from post
                    $original_value = isset( $post->$post_field ) ? $post->$post_field : '';
                    
                    if ( empty( $original_value ) ) {
                        continue;
                    }

                    // Flatten both original and translation to a single text block
                    // so TranslatePress always sees ONE string = ONE dictionary entry
                    $original_flat  = $this->convert_linebreaks( $original_value );
                    $translated_flat = $this->convert_linebreaks( $translated_value );

                    // Persist flattened original to DB (bypass WooCommerce hooks)
                    if ( $original_flat !== $original_value && in_array( $post_field, array( 'post_content', 'post_excerpt' ), true ) ) {
                        global $wpdb;
                        $updated = $wpdb->update(
                            $wpdb->posts,
                            array( $post_field => $original_flat ),
                            array( 'ID' => $post_id ),
                            array( '%s' ),
                            array( '%d' )
                        );
                        if ( $updated !== false ) {
                            clean_post_cache( $post_id );
                            $this->log( sprintf( 'Flattened %s for post #%d (%d chars)', $post_field, $post_id, strlen( $original_flat ) ) );
                        }
                    }

                    $result = $this->force_insert_translation( $original_flat, $translated_flat, $lang_code );

                    if ( ! is_wp_error( $result ) && isset( $result['success'] ) && $result['success'] ) {
                        $translations_added++;
                    } elseif ( is_wp_error( $result ) ) {
                        $this->log( sprintf( 'API Error for post #%d, field %s: %s', $post_id, $meta_key, $result->get_error_message() ) );
                    }

                    // Clean up temporary meta field
                    delete_post_meta( $post_id, $meta_key );
                }
            }

            // Log for debugging
            if ( $translations_added > 0 ) {
                $this->log( sprintf( 'Added %d translations for post #%d', $translations_added, $post_id ) );
            }

            // Clean up legacy convert flag if present (no longer required)
            delete_post_meta( $post_id, '_trp_convert_linebreaks' );

            // Sync category translations (WooCommerce products)
            $this->sync_category_translations( $post_id, $all_meta, $translation_languages );

            // Sync attribute translations (WooCommerce products)
            $this->sync_attribute_translations( $post_id, $all_meta, $translation_languages );

            // Sync variation description translations (WooCommerce variations)
            $this->sync_variation_description_translations( $post_id, $all_meta, $translation_languages );

            // Sync post/product slug translations (uses _trp_slug_[lang] or auto-derives from translated title)
            $this->sync_post_slug_translations( $post_id, $post, $all_meta, $translation_languages );

        } catch ( Exception $e ) {
            $this->log( 'Error in sync_translations for post #' . $post_id . ': ' . $e->getMessage() );
        }
    }

    /**
     * Safety net: normalize post_content and post_excerpt AFTER entire import record is done
     * This runs on pmxi_after_post_import, which fires after pmxi_saved_post and all
     * WooCommerce/ACF processing. If content still has paragraph breaks at this point,
     * we force-normalize it via direct DB update.
     * 
     * @param int $post_id Post ID
     */
    public function normalize_content_after_import( $post_id ) {
        global $wpdb;
        
        $post = get_post( $post_id );
        if ( ! $post ) {
            return;
        }
        
        $fields_to_check = array( 'post_content', 'post_excerpt' );
        
        foreach ( $fields_to_check as $field ) {
            $value = $post->$field;
            if ( empty( $value ) ) {
                continue;
            }
            
            $normalized = $this->convert_linebreaks( $value );
            
            if ( $normalized !== $value ) {
                $updated = $wpdb->update(
                    $wpdb->posts,
                    array( $field => $normalized ),
                    array( 'ID' => $post_id ),
                    array( '%s' ),
                    array( '%d' )
                );
                if ( $updated !== false ) {
                    clean_post_cache( $post_id );
                    $this->log( sprintf( 'SAFETY NET: Normalized %s for post #%d "%s" (still had paragraph breaks after import)', $field, $post_id, $post->post_title ) );
                }
            }
        }
    }

    /**
     * Sync category translations for WooCommerce products
     * 
     * @param int   $post_id              Post ID
     * @param array $all_meta             All post meta
     * @param array $translation_languages Languages to translate to
     */
    private function sync_category_translations( $post_id, $all_meta, $translation_languages ) {
        // Check if this is a WooCommerce product
        if ( get_post_type( $post_id ) !== 'product' ) {
            return;
        }

        // Get assigned product categories
        $categories = wp_get_post_terms( $post_id, 'product_cat', array( 'fields' => 'all' ) );
        if ( is_wp_error( $categories ) || empty( $categories ) ) {
            return;
        }

        $translations_added = 0;

        foreach ( $translation_languages as $lang_code ) {
            // Category name translations
            $cat_meta_key = $this->cat_prefix . $lang_code;
            if ( isset( $all_meta[ $cat_meta_key ] ) && ! empty( $all_meta[ $cat_meta_key ][0] ) ) {
                $translated_cats = array_map( 'trim', explode( '|', $all_meta[ $cat_meta_key ][0] ) );
                
                // Match translated categories with original categories by position
                foreach ( $categories as $index => $category ) {
                    if ( isset( $translated_cats[ $index ] ) && ! empty( $translated_cats[ $index ] ) ) {
                        $result = $this->force_insert_translation(
                            $category->name,
                            $translated_cats[ $index ],
                            $lang_code
                        );
                        
                        if ( ! is_wp_error( $result ) && isset( $result['success'] ) && $result['success'] ) {
                            $translations_added++;
                        }
                    }
                }
                
                delete_post_meta( $post_id, $cat_meta_key );
            }

            // Category slug translations (requires SEO Pack)
            $cat_slug_meta_key = $this->cat_slug_prefix . $lang_code;
            if ( isset( $all_meta[ $cat_slug_meta_key ] ) && ! empty( $all_meta[ $cat_slug_meta_key ][0] ) ) {
                if ( function_exists( 'trpc_insert_slug_translation' ) ) {
                    $translated_slugs = array_map( 'trim', explode( '|', $all_meta[ $cat_slug_meta_key ][0] ) );
                    
                    foreach ( $categories as $index => $category ) {
                        if ( isset( $translated_slugs[ $index ] ) && ! empty( $translated_slugs[ $index ] ) ) {
                            $result = trpc_insert_slug_translation(
                                $category->slug,
                                $translated_slugs[ $index ],
                                $lang_code,
                                array( 'type' => 'term', 'status' => 2 )
                            );
                            
                            if ( ! is_wp_error( $result ) && isset( $result['success'] ) && $result['success'] ) {
                                $translations_added++;
                            }
                        }
                    }
                } else {
                    $this->log( 'SEO Pack required for slug translations, skipping category slugs' );
                }
                
                delete_post_meta( $post_id, $cat_slug_meta_key );
            }
        }

        if ( $translations_added > 0 ) {
            $this->log( sprintf( 'Added %d category translations for post #%d', $translations_added, $post_id ) );
        }
    }

    /**
     * Sync attribute translations for WooCommerce products
     * 
     * Custom field format: _trp_attr_[attribute_slug]_[lang_code]
     * Example: _trp_attr_color_fr_CA = "Rouge|Bleu|Vert"
     * 
     * @param int   $post_id              Post ID
     * @param array $all_meta             All post meta
     * @param array $translation_languages Languages to translate to
     */
    private function sync_attribute_translations( $post_id, $all_meta, $translation_languages ) {
        // Check if this is a WooCommerce product or variation
        $post_type = get_post_type( $post_id );
        
        if ( ! in_array( $post_type, array( 'product', 'product_variation' ), true ) || ! function_exists( 'wc_get_product' ) ) {
            return;
        }

        $product = wc_get_product( $post_id );
        if ( ! $product ) {
            return;
        }

        // For variations, get parent product to access attributes
        $parent_product = $product;
        $terms_post_id = $post_id; // Post ID to get terms from
        if ( $product->is_type( 'variation' ) ) {
            $parent_id = $product->get_parent_id();
            $parent_product = wc_get_product( $parent_id );
            if ( ! $parent_product ) {
                return;
            }
            $terms_post_id = $parent_id; // Get terms from parent for variations
        }

        $attributes = $parent_product->get_attributes();
        if ( empty( $attributes ) ) {
            return;
        }

        $translations_added = 0;

        // Find all attribute translation meta keys
        foreach ( $all_meta as $meta_key => $meta_values ) {
            if ( strpos( $meta_key, $this->attr_prefix ) !== 0 ) {
                continue;
            }

            // Parse the meta key: _trp_attr_[attribute_slug]_[lang_code]
            $suffix = substr( $meta_key, strlen( $this->attr_prefix ) );
            
            // Find which language this is for
            $lang_code = null;
            $attr_slug = null;
            
            foreach ( $translation_languages as $lang ) {
                if ( substr( $suffix, -strlen( $lang ) - 1 ) === '_' . $lang ) {
                    $lang_code = $lang;
                    $attr_slug = substr( $suffix, 0, -strlen( $lang ) - 1 );
                    break;
                }
            }

            if ( ! $lang_code || ! $attr_slug || empty( $meta_values[0] ) ) {
                continue;
            }

            // Handle case where user already included 'pa_' prefix in meta key
            // e.g., _trp_attr_pa_magnetic_fr_CA -> attr_slug = 'pa_magnetic'
            if ( strpos( $attr_slug, 'pa_' ) === 0 ) {
                $attr_slug = substr( $attr_slug, 3 ); // Remove 'pa_' prefix
            }

            // Get original attribute values
            $taxonomy = 'pa_' . $attr_slug;
            
            if ( isset( $attributes[ $taxonomy ] ) && $attributes[ $taxonomy ]->is_taxonomy() ) {
                // Global attribute (taxonomy-based)
                $translated_values = array_map( 'trim', explode( '|', $meta_values[0] ) );
                
                // For variations, get the specific attribute value this variation uses
                if ( $product->is_type( 'variation' ) ) {
                    $variation_attr_value = $product->get_attribute( $taxonomy );
                    
                    if ( ! empty( $variation_attr_value ) && ! empty( $translated_values[0] ) ) {
                        $result = $this->force_insert_translation(
                            $variation_attr_value,
                            $translated_values[0],
                            $lang_code
                        );
                        
                        if ( ! is_wp_error( $result ) && isset( $result['success'] ) && $result['success'] ) {
                            $translations_added++;
                        }
                    }
                } else {
                    // For parent products, match all terms by index (pipe-separated values)
                    $terms = wp_get_post_terms( $terms_post_id, $taxonomy, array( 'fields' => 'all' ) );
                    
                    if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
                        foreach ( $terms as $index => $term ) {
                            if ( isset( $translated_values[ $index ] ) && ! empty( $translated_values[ $index ] ) ) {
                                $result = $this->force_insert_translation(
                                    $term->name,
                                    $translated_values[ $index ],
                                    $lang_code
                                );
                                
                                if ( ! is_wp_error( $result ) && isset( $result['success'] ) && $result['success'] ) {
                                    $translations_added++;
                                }
                            }
                        }
                    }
                }
            } elseif ( isset( $attributes[ $attr_slug ] ) && ! $attributes[ $attr_slug ]->is_taxonomy() ) {
                // Local/custom attribute (stored in product data)
                $original_values = $attributes[ $attr_slug ]->get_options();
                $translated_values = array_map( 'trim', explode( '|', $meta_values[0] ) );
                
                foreach ( $original_values as $index => $original_value ) {
                    if ( isset( $translated_values[ $index ] ) && ! empty( $translated_values[ $index ] ) ) {
                        $result = $this->force_insert_translation(
                            $original_value,
                            $translated_values[ $index ],
                            $lang_code
                        );
                        
                        if ( ! is_wp_error( $result ) && isset( $result['success'] ) && $result['success'] ) {
                            $translations_added++;
                        }
                    }
                }
            }

            delete_post_meta( $post_id, $meta_key );
        }

        if ( $translations_added > 0 ) {
            $this->log( sprintf( 'Added %d attribute translations for post #%d', $translations_added, $post_id ) );
        }
    }

    /**
     * Sync variation description translations for WooCommerce product variations
     * 
     * Custom field format: _trp_variation_desc_[lang_code]
     * Example: _trp_variation_desc_fr_CA = "Description traduite de la variation"
     * 
     * In WP All Import, map:
     * - _variation_description → Your default language variation description
     * - _trp_variation_desc_fr_CA → Your translated variation description
     * 
     * @param int   $post_id              Post ID
     * @param array $all_meta             All post meta
     * @param array $translation_languages Languages to translate to
     */
    private function sync_variation_description_translations( $post_id, $all_meta, $translation_languages ) {
        // Check if this is a WooCommerce product variation
        if ( get_post_type( $post_id ) !== 'product_variation' ) {
            return;
        }

        // Get the original variation description
        $original_desc = isset( $all_meta['_variation_description'] ) ? $all_meta['_variation_description'][0] : '';
        
        if ( empty( $original_desc ) ) {
            return;
        }

        $translations_added = 0;

        foreach ( $translation_languages as $lang_code ) {
            $meta_key = $this->variation_desc_prefix . $lang_code;
            
            if ( ! isset( $all_meta[ $meta_key ] ) || empty( $all_meta[ $meta_key ][0] ) ) {
                continue;
            }

            $translated_desc = $all_meta[ $meta_key ][0];

            // Flatten both to single block
            $original_flat  = $this->convert_linebreaks( $original_desc );
            $translated_flat = $this->convert_linebreaks( $translated_desc );

            $result = $this->force_insert_translation( $original_flat, $translated_flat, $lang_code );

            if ( ! is_wp_error( $result ) && isset( $result['success'] ) && $result['success'] ) {
                $translations_added++;
            } elseif ( is_wp_error( $result ) ) {
                $this->log( sprintf( 'API Error for variation #%d description: %s', $post_id, $result->get_error_message() ) );
            }

            // Keep the meta field for direct lookup by theme (don't delete)
            // This allows themes to read translation directly without dictionary lookup
        }

        if ( $translations_added > 0 ) {
            $this->log( sprintf( 'Added %d variation description translations for variation #%d', $translations_added, $post_id ) );
        }
    }

    /**
     * Force insert/replace a slug translation in TranslatePress' SEO Pack tables.
     *
     * TranslatePress' slug query uses INSERT IGNORE, so an existing FR slug for
     * the same default-language slug + language won't be replaced on re-import.
     * This helper deletes the existing translation row first, then calls the
     * official Custom API so the new translation is what ends up in the DB.
     *
     * @param string $original_slug   The default-language slug (post_name / term slug).
     * @param string $translated_slug The translated slug (already sanitized).
     * @param string $lang_code       Target language code (e.g. fr_CA).
     * @param string $type            Slug type: 'post', 'term', 'taxonomy', 'post-type-base', 'other'.
     * @return array|WP_Error
     */
    private function force_insert_slug_translation( $original_slug, $translated_slug, $lang_code, $type = 'post' ) {
        global $wpdb;

        if ( ! function_exists( 'trpc_insert_slug_translation' ) ) {
            return new WP_Error( 'missing_seo_pack', 'TranslatePress SEO Pack required for slug translations.' );
        }

        $original_table    = $wpdb->prefix . 'trp_slug_originals';
        $translation_table = $wpdb->prefix . 'trp_slug_translations';

        // Match the normalisation used inside TRP_Slug_Query::insert_original_slugs().
        $original_normalized = strtolower( urlencode( urldecode( $original_slug ) ) );

        $original_id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM `{$original_table}` WHERE original = %s AND type = %s LIMIT 1",
                $original_normalized,
                $type
            )
        );

        if ( $original_id ) {
            $wpdb->delete(
                $translation_table,
                array(
                    'original_id' => $original_id,
                    'language'    => $lang_code,
                ),
                array( '%d', '%s' )
            );
        }

        return trpc_insert_slug_translation(
            $original_slug,
            $translated_slug,
            $lang_code,
            array(
                'type'   => $type,
                'status' => 2,
            )
        );
    }

    /**
     * Sync post/product slug translations.
     *
     * Sources, in priority order, per language:
     *   1. Explicit _trp_slug_[lang] meta value (sanitised via sanitize_title()).
     *   2. Auto-derived from _trp_title_[lang] (default ON, toggleable in settings).
     *
     * Skips product variations (no user-facing URL) and posts without a post_name.
     *
     * @param int     $post_id               Post ID.
     * @param WP_Post $post                  Post object (default-language data).
     * @param array   $all_meta              All post meta.
     * @param array   $translation_languages Languages to translate to.
     */
    private function sync_post_slug_translations( $post_id, $post, $all_meta, $translation_languages ) {
        if ( ! function_exists( 'trpc_insert_slug_translation' ) ) {
            $this->log( 'SEO Pack required for post slug translations, skipping' );
            return;
        }

        if ( empty( $post ) || empty( $post->post_name ) ) {
            return;
        }

        // Variations don't have public URLs of their own.
        if ( $post->post_type === 'product_variation' ) {
            return;
        }

        $auto_derive_enabled = (bool) get_option( 'oli_import_sync_trp_auto_slug_enabled', 1 );
        $original_slug       = $post->post_name;
        $translations_added  = 0;

        foreach ( $translation_languages as $lang_code ) {
            $explicit_key = $this->slug_prefix . $lang_code;
            $title_key    = '_trp_title_' . $lang_code;

            $translated_slug = '';
            $source          = '';

            if ( isset( $all_meta[ $explicit_key ] ) && ! empty( $all_meta[ $explicit_key ][0] ) ) {
                $translated_slug = sanitize_title( $all_meta[ $explicit_key ][0] );
                $source          = 'explicit ' . $explicit_key;
            } elseif ( $auto_derive_enabled && isset( $all_meta[ $title_key ] ) && ! empty( $all_meta[ $title_key ][0] ) ) {
                $translated_slug = sanitize_title( $all_meta[ $title_key ][0] );
                $source          = 'auto-derived from ' . $title_key;
            }

            if ( empty( $translated_slug ) ) {
                continue;
            }

            // Mirror WordPress's "-2", "-3" auto-disambiguator from the EN slug onto the FR slug.
            // Two products with identical FR titles (e.g. same product, different SKU/finish) would
            // otherwise produce the same FR slug, and TranslatePress' make_slugs_unique() strips a
            // trailing "-\d+" before appending its own number, which clobbers titles whose own
            // text ends in a number (e.g. "...inoxydable-304" turns into "...inoxydable-305").
            // Mirroring the EN suffix bypasses that, only when EN's "-N" really is a WP duplicate
            // suffix (i.e. another post owns the base slug) and N is a small integer (2..999).
            if ( 'explicit ' . $explicit_key !== $source && preg_match( '/^(.+)-(\d{1,3})$/', $original_slug, $m ) ) {
                $en_base = $m[1];
                $en_num  = (int) $m[2];
                if ( $en_num >= 2 ) {
                    global $wpdb;
                    $base_owner = $wpdb->get_var( $wpdb->prepare(
                        "SELECT ID FROM {$wpdb->posts} WHERE post_name = %s AND ID <> %d AND post_status NOT IN ('trash','auto-draft') LIMIT 1",
                        $en_base,
                        $post_id
                    ) );
                    if ( $base_owner && ! preg_match( '/-' . $en_num . '$/', $translated_slug ) ) {
                        $mirrored_slug = $translated_slug . '-' . $en_num;
                        $this->log( sprintf( 'Slug disambiguator mirrored [%s] post #%d "%s" -> "%s" (EN suffix -%d)', $lang_code, $post_id, $translated_slug, $mirrored_slug, $en_num ) );
                        $translated_slug = $mirrored_slug;
                    }
                }
            }

            // Don't insert a "translation" that's identical to the original.
            if ( $translated_slug === $original_slug ) {
                $this->log( sprintf( 'Skip slug translation for post #%d (%s): translated slug identical to original ("%s")', $post_id, $lang_code, $original_slug ) );
                if ( isset( $all_meta[ $explicit_key ] ) ) {
                    delete_post_meta( $post_id, $explicit_key );
                }
                continue;
            }

            $result = $this->force_insert_slug_translation( $original_slug, $translated_slug, $lang_code, 'post' );

            if ( ! is_wp_error( $result ) && isset( $result['success'] ) && $result['success'] ) {
                $translations_added++;
                $this->log( sprintf( 'Slug translated [%s] post #%d "%s" to "%s" (%s)', $lang_code, $post_id, $original_slug, $translated_slug, $source ) );
            } elseif ( is_wp_error( $result ) ) {
                $this->log( sprintf( 'Slug API error for post #%d (%s): %s', $post_id, $lang_code, $result->get_error_message() ) );
            }

            if ( isset( $all_meta[ $explicit_key ] ) ) {
                delete_post_meta( $post_id, $explicit_key );
            }
        }

        if ( $translations_added > 0 ) {
            $this->log( sprintf( 'Added %d post slug translations for post #%d', $translations_added, $post_id ) );
        }
    }

    /**
     * Show admin notice with usage instructions
     */
    public function admin_notice() {
        $screen = get_current_screen();
        
        // Only show on WP All Import pages
        if ( ! $screen || strpos( $screen->id, 'pmxi' ) === false ) {
            return;
        }
        if ( ! function_exists( 'trpc_insert_translation' ) ) {
            echo '<div class="notice notice-error"><p><strong>' . esc_html__( 'Oli Import Sync for TranslatePress', 'oli-import-sync-for-translatepress' ) . ':</strong> ';
            echo esc_html__( 'TranslatePress Custom API is required.', 'oli-import-sync-for-translatepress' );
            echo ' <a href="' . esc_url( admin_url( 'plugins.php' ) ) . '">' . esc_html__( 'Manage Plugins', 'oli-import-sync-for-translatepress' ) . '</a></p></div>';
            return;
        }
        $url = admin_url( 'options-general.php?page=oli-import-sync-trp' );
        echo '<div class="notice notice-info is-dismissible"><p><strong>' . esc_html__( 'Oli Import Sync for TranslatePress', 'oli-import-sync-for-translatepress' ) . '</strong> ';
        echo esc_html__( 'is active.', 'oli-import-sync-for-translatepress' );
        echo ' <a href="' . esc_url( $url ) . '">' . esc_html__( 'View field reference & logs →', 'oli-import-sync-for-translatepress' ) . '</a></p></div>';
    }

    /**
     * Register admin menu page under Settings
     */
    public function add_admin_menu() {
        add_options_page(
            __( 'Oli Import Sync for TranslatePress', 'oli-import-sync-for-translatepress' ),
            __( 'Oli Import Sync', 'oli-import-sync-for-translatepress' ),
            'manage_options',
            'oli-import-sync-trp',
            array( $this, 'render_admin_page' )
        );

        add_submenu_page(
            'options.php',
            __( 'Oli Import Sync for TranslatePress', 'oli-import-sync-for-translatepress' ),
            __( 'Oli Import Sync', 'oli-import-sync-for-translatepress' ),
            'manage_options',
            'wpai-trp-sync',
            array( $this, 'redirect_legacy_settings_page' )
        );
    }

    /**
     * Send bookmarks of the old settings slug to the new page.
     *
     * @return void
     */
    public function redirect_legacy_settings_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if ( 'wpai-trp-sync' !== $page ) {
            return;
        }

        $args = array( 'page' => 'oli-import-sync-trp' );
        $tab  = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if ( '' !== $tab ) {
            $args['tab'] = $tab;
        }

        wp_safe_redirect( add_query_arg( $args, admin_url( 'options-general.php' ) ) );
        exit;
    }

    /**
     * Register settings
     */
    public function register_settings() {
        register_setting(
            'oli_import_sync_trp_settings',
            'oli_import_sync_trp_logging_enabled',
            array(
                'type'              => 'integer',
                'sanitize_callback' => 'absint',
                'default'           => 0,
            )
        );

        register_setting(
            'oli_import_sync_trp_settings',
            'oli_import_sync_trp_auto_slug_enabled',
            array(
                'type'              => 'integer',
                'sanitize_callback' => 'absint',
                'default'           => 1,
            )
        );
    }

    /**
     * Handle clear log action
     */
    public function handle_clear_log() {
        if ( ! isset( $_POST['oli_import_sync_trp_clear_log'] ) ) {
            return;
        }
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        check_admin_referer( 'oli_import_sync_trp_clear_log' );
        if ( file_exists( $this->log_file ) ) {
            wp_delete_file( $this->log_file );
        }
        wp_safe_redirect( admin_url( 'options-general.php?page=oli-import-sync-trp&tab=logs&cleared=1' ) );
        exit;
    }

    /**
     * Render the main admin page with tabs
     */
    public function render_admin_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Sorry, you are not allowed to access this page.', 'oli-import-sync-for-translatepress' ) );
        }

        $active_tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'dashboard'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $languages  = $this->get_translation_languages();
        $tabs       = array(
            'dashboard' => __( 'Dashboard', 'oli-import-sync-for-translatepress' ),
            'fields'    => __( 'Field Reference', 'oli-import-sync-for-translatepress' ),
            'logs'      => __( 'Logs', 'oli-import-sync-for-translatepress' ),
        );
        if ( ! isset( $tabs[ $active_tab ] ) ) {
            $active_tab = 'dashboard';
        }
        ?>
        <div class="wrap">
            <h1>
                <?php echo esc_html__( 'Oli Import Sync for TranslatePress', 'oli-import-sync-for-translatepress' ); ?>
                <small class="oli-import-sync-trp-heading-meta">v<?php echo esc_html( OLI_IMPORT_SYNC_TRP_VERSION ); ?></small>
            </h1>
            <nav class="nav-tab-wrapper">
                <?php foreach ( $tabs as $tab => $label ) : ?>
                    <a href="<?php echo esc_url( admin_url( 'options-general.php?page=oli-import-sync-trp&tab=' . rawurlencode( $tab ) ) ); ?>" class="<?php echo esc_attr( 'nav-tab' . ( $active_tab === $tab ? ' nav-tab-active' : '' ) ); ?>"><?php echo esc_html( $label ); ?></a>
                <?php endforeach; ?>
            </nav>
            <div class="oli-import-sync-trp-tabs-body">
            <?php
            switch ( $active_tab ) {
                case 'fields':
                    $this->render_tab_fields( $languages );
                    break;
                case 'logs':
                    $this->render_tab_logs();
                    break;
                default:
                    $this->render_tab_dashboard( $languages );
                    break;
            }
            ?>
            </div>
            <p class="description oli-import-sync-trp-footer">
                <?php
                echo wp_kses(
                    sprintf(
                        /* translators: %s: donate URL */
                        __( 'If this plugin helps you, please consider a <a href="%s" target="_blank" rel="noopener noreferrer">donation</a>. Thank you!', 'oli-import-sync-for-translatepress' ),
                        esc_url( OLI_IMPORT_SYNC_TRP_DONATE_URL )
                    ),
                    array(
                        'a' => array(
                            'href'   => true,
                            'target' => true,
                            'rel'    => true,
                        ),
                    )
                );
                ?>
            </p>
        </div>
        <?php
    }

    /**
     * Allowed HTML for dashboard status cells.
     *
     * @return array<string, array<string, bool>>
     */
    private function allowed_status_html() {
        return array(
            'span' => array(
                'class'       => true,
                'aria-hidden' => true,
            ),
            'a'    => array(
                'href' => true,
            ),
            'em'   => array(
                'class' => true,
            ),
        );
    }

    /**
     * Status icon markup.
     *
     * @param bool $ok Whether the check passed.
     * @return string
     */
    private function status_mark( $ok ) {
        if ( $ok ) {
            return '<span class="oli-import-sync-trp-ok" aria-hidden="true">&#10003;</span> ';
        }
        return '<span class="oli-import-sync-trp-no" aria-hidden="true">&#10007;</span> ';
    }

    /**
     * Copy-to-clipboard control for a field name.
     *
     * @param string $field Field name.
     * @param bool   $small Smaller code style.
     * @return void
     */
    private function render_copy_field( $field, $small = false ) {
        $code_class = $small ? 'oli-import-sync-trp-field-code-sm' : '';
        echo '<span class="oli-import-sync-trp-field"><code' . ( $code_class ? ' class="' . esc_attr( $code_class ) . '"' : '' ) . '>' . esc_html( $field ) . '</code>';
        echo '<button type="button" class="oli-import-sync-trp-copy" data-v="' . esc_attr( $field ) . '">' . esc_html__( 'copy', 'oli-import-sync-for-translatepress' ) . '</button></span>';
    }

    /**
     * Dashboard tab — system status and how-it-works
     */
    private function render_tab_dashboard( $languages ) {
        $has_trp   = function_exists( 'trpc_insert_translation' );
        $has_wc    = class_exists( 'WooCommerce' );
        $has_wpai  = class_exists( 'PMXI_Plugin' );
        $logs_url  = admin_url( 'options-general.php?page=oli-import-sync-trp&tab=logs' );
        $fields_url = admin_url( 'options-general.php?page=oli-import-sync-trp&tab=fields' );
        $allowed   = $this->allowed_status_html();

        $tp_status = class_exists( 'TRP_Translate_Press' )
            ? $this->status_mark( true ) . esc_html__( 'Active', 'oli-import-sync-for-translatepress' )
            : $this->status_mark( false ) . esc_html__( 'Not found', 'oli-import-sync-for-translatepress' );

        $api_status = $has_trp
            ? $this->status_mark( true ) . esc_html__( 'Active', 'oli-import-sync-for-translatepress' )
            : $this->status_mark( false ) . esc_html__( 'Required', 'oli-import-sync-for-translatepress' ) . ' — <a href="' . esc_url( admin_url( 'plugins.php' ) ) . '">' . esc_html__( 'install it', 'oli-import-sync-for-translatepress' ) . '</a>';

        $wpai_status = $has_wpai
            ? $this->status_mark( true ) . esc_html__( 'Active', 'oli-import-sync-for-translatepress' )
            : $this->status_mark( false ) . esc_html__( 'Not found', 'oli-import-sync-for-translatepress' );

        $wc_status = $has_wc
            ? $this->status_mark( true ) . esc_html__( 'Active', 'oli-import-sync-for-translatepress' )
            : esc_html__( 'Not required', 'oli-import-sync-for-translatepress' );

        $lang_status = ! empty( $languages )
            ? esc_html( implode( ', ', $languages ) )
            : $this->status_mark( false ) . esc_html__( 'None detected', 'oli-import-sync-for-translatepress' );

        $logging_status = get_option( 'oli_import_sync_trp_logging_enabled', 0 )
            ? $this->status_mark( true ) . esc_html__( 'Enabled', 'oli-import-sync-for-translatepress' )
            : esc_html__( 'Disabled', 'oli-import-sync-for-translatepress' );
        $logging_status .= ' (<a href="' . esc_url( $logs_url ) . '">' . esc_html__( 'manage', 'oli-import-sync-for-translatepress' ) . '</a>)';

        $slug_status = get_option( 'oli_import_sync_trp_auto_slug_enabled', 1 )
            ? $this->status_mark( true ) . esc_html__( 'Enabled', 'oli-import-sync-for-translatepress' )
            : esc_html__( 'Disabled', 'oli-import-sync-for-translatepress' );
        if ( ! function_exists( 'trpc_insert_slug_translation' ) ) {
            $slug_status .= ' <em class="oli-import-sync-trp-warn">' . esc_html__( '(SEO Pack required)', 'oli-import-sync-for-translatepress' ) . '</em>';
        }
        $slug_status .= ' (<a href="' . esc_url( $logs_url ) . '">' . esc_html__( 'manage', 'oli-import-sync-for-translatepress' ) . '</a>)';
        ?>
        <div class="card oli-import-sync-trp-card">
            <h2><?php echo esc_html__( 'System Status', 'oli-import-sync-for-translatepress' ); ?></h2>
            <table class="widefat striped oli-import-sync-trp-status">
                <tbody>
                    <tr><td><strong><?php echo esc_html__( 'TranslatePress', 'oli-import-sync-for-translatepress' ); ?></strong></td><td><?php echo wp_kses( $tp_status, $allowed ); ?></td></tr>
                    <tr><td><strong><?php echo esc_html__( 'TranslatePress Custom API', 'oli-import-sync-for-translatepress' ); ?></strong></td><td><?php echo wp_kses( $api_status, $allowed ); ?></td></tr>
                    <tr><td><strong><?php echo esc_html__( 'WP All Import', 'oli-import-sync-for-translatepress' ); ?></strong></td><td><?php echo wp_kses( $wpai_status, $allowed ); ?></td></tr>
                    <tr><td><strong><?php echo esc_html__( 'WooCommerce', 'oli-import-sync-for-translatepress' ); ?></strong></td><td><?php echo wp_kses( $wc_status, $allowed ); ?></td></tr>
                    <tr><td><strong><?php echo esc_html__( 'Translation Languages', 'oli-import-sync-for-translatepress' ); ?></strong></td><td><?php echo wp_kses( $lang_status, $allowed ); ?></td></tr>
                    <tr><td><strong><?php echo esc_html__( 'Logging', 'oli-import-sync-for-translatepress' ); ?></strong></td><td><?php echo wp_kses( $logging_status, $allowed ); ?></td></tr>
                    <tr><td><strong><?php echo esc_html__( 'Auto-derive Post Slug from Title', 'oli-import-sync-for-translatepress' ); ?></strong></td><td><?php echo wp_kses( $slug_status, $allowed ); ?></td></tr>
                </tbody>
            </table>
        </div>
        <div class="card oli-import-sync-trp-card oli-import-sync-trp-card-spaced">
            <h2><?php echo esc_html__( 'How It Works', 'oli-import-sync-for-translatepress' ); ?></h2>
            <ol>
                <li><?php echo wp_kses( __( 'Map your <strong>default language</strong> fields normally in WP All Import (Title, Content, Excerpt).', 'oli-import-sync-for-translatepress' ), array( 'strong' => array() ) ); ?></li>
                <li><?php
                echo wp_kses(
                    sprintf(
                        /* translators: %s: URL of the Field Reference tab. */
                        __( 'Add <strong>custom fields</strong> for each translation (see <a href="%s">Field Reference</a>).', 'oli-import-sync-for-translatepress' ),
                        esc_url( $fields_url )
                    ),
                    array(
                        'strong' => array(),
                        'a'      => array( 'href' => true ),
                    )
                );
                ?></li>
                <li><?php echo wp_kses( __( 'Run the import — translations are <strong>automatically synced</strong> to TranslatePress.', 'oli-import-sync-for-translatepress' ), array( 'strong' => array() ) ); ?></li>
            </ol>
            <h3><?php echo esc_html__( 'Automatic Features', 'oli-import-sync-for-translatepress' ); ?></h3>
            <ul class="oli-import-sync-trp-list">
                <li><?php echo wp_kses( __( 'Multi-paragraph content is flattened so TranslatePress detects <strong>one string per description</strong>.', 'oli-import-sync-for-translatepress' ), array( 'strong' => array() ) ); ?></li>
                <li><?php echo wp_kses( __( 'Inline formatting (<code>&lt;strong&gt;</code>, <code>&lt;em&gt;</code>, <code>&lt;a&gt;</code>) is preserved.', 'oli-import-sync-for-translatepress' ), array( 'code' => array() ) ); ?></li>
                <li><?php echo wp_kses( __( 'All translations are saved as <strong>Human Reviewed</strong> (status 2).', 'oli-import-sync-for-translatepress' ), array( 'strong' => array() ) ); ?></li>
                <li><?php echo wp_kses( __( 'Smart quotes from <code>wptexturize()</code> are handled automatically.', 'oli-import-sync-for-translatepress' ), array( 'code' => array() ) ); ?></li>
            </ul>
        </div>
        <?php
    }

    /**
     * Field Reference tab — auto-detected fields with copy buttons
     */
    private function render_tab_fields( $languages ) {
        ?>
        <div class="oli-import-sync-trp-section">
            <h3><?php echo esc_html__( 'Post / Product Fields', 'oli-import-sync-for-translatepress' ); ?></h3>
            <table class="widefat striped">
                <thead><tr><th><?php echo esc_html__( 'Purpose', 'oli-import-sync-for-translatepress' ); ?></th><?php foreach ( $languages as $l ) echo '<th>' . esc_html( $l ) . '</th>'; ?></tr></thead>
                <tbody>
                <?php
                $fields = array(
                    __( 'Translated Title', 'oli-import-sync-for-translatepress' )   => '_trp_title_',
                    __( 'Translated Content', 'oli-import-sync-for-translatepress' ) => '_trp_content_',
                    __( 'Translated Excerpt', 'oli-import-sync-for-translatepress' ) => '_trp_excerpt_',
                    __( 'Translated Slug (optional, SEO Pack required - leave empty to auto-derive from translated title)', 'oli-import-sync-for-translatepress' ) => '_trp_slug_',
                );
                foreach ( $fields as $label => $prefix ) {
                    echo '<tr><td><strong>' . esc_html( $label ) . '</strong></td>';
                    foreach ( $languages as $lang ) {
                        echo '<td>';
                        $this->render_copy_field( $prefix . $lang );
                        echo '</td>';
                    }
                    echo '</tr>';
                }
                ?>
                </tbody>
            </table>
        </div>

        <?php if ( class_exists( 'WooCommerce' ) ) : ?>

        <div class="oli-import-sync-trp-section">
            <h3><?php echo esc_html__( 'WooCommerce Variations', 'oli-import-sync-for-translatepress' ); ?></h3>
            <table class="widefat striped">
                <thead><tr><th><?php echo esc_html__( 'Purpose', 'oli-import-sync-for-translatepress' ); ?></th><?php foreach ( $languages as $l ) echo '<th>' . esc_html( $l ) . '</th>'; ?></tr></thead>
                <tbody><tr><td><strong><?php echo esc_html__( 'Variation Description', 'oli-import-sync-for-translatepress' ); ?></strong></td>
                <?php foreach ( $languages as $lang ) {
                    echo '<td>';
                    $this->render_copy_field( '_trp_variation_desc_' . $lang );
                    echo '</td>';
                } ?>
                </tr></tbody>
            </table>
        </div>

        <div class="oli-import-sync-trp-section">
            <h3><?php echo esc_html__( 'WooCommerce Categories', 'oli-import-sync-for-translatepress' ); ?></h3>
            <table class="widefat striped">
                <thead><tr><th><?php echo esc_html__( 'Purpose', 'oli-import-sync-for-translatepress' ); ?></th><?php foreach ( $languages as $l ) echo '<th>' . esc_html( $l ) . '</th>'; ?></tr></thead>
                <tbody>
                <?php
                $cat_fields = array(
                    __( 'Category Names (pipe-separated)', 'oli-import-sync-for-translatepress' ) => '_trp_cat_',
                    __( 'Category Slugs (SEO Pack required)', 'oli-import-sync-for-translatepress' ) => '_trp_cat_slug_',
                );
                foreach ( $cat_fields as $label => $prefix ) {
                    echo '<tr><td><strong>' . esc_html( $label ) . '</strong></td>';
                    foreach ( $languages as $lang ) {
                        echo '<td>';
                        $this->render_copy_field( $prefix . $lang );
                        echo '</td>';
                    }
                    echo '</tr>';
                }
                ?>
                </tbody>
            </table>
        </div>

        <?php if ( function_exists( 'wc_get_attribute_taxonomies' ) ) :
            $attributes = wc_get_attribute_taxonomies();
            if ( ! empty( $attributes ) ) : ?>
        <div class="oli-import-sync-trp-section">
            <h3><?php echo esc_html__( 'WooCommerce Attributes', 'oli-import-sync-for-translatepress' ); ?> <small class="oli-import-sync-trp-section-meta">(<?php echo esc_html( (string) count( $attributes ) ); ?> <?php echo esc_html__( 'detected', 'oli-import-sync-for-translatepress' ); ?>)</small></h3>
            <p class="oli-import-sync-trp-help"><?php echo wp_kses( __( 'Values should be pipe-separated, e.g. <code>"Rouge|Bleu|Vert"</code>', 'oli-import-sync-for-translatepress' ), array( 'code' => array() ) ); ?></p>
            <div class="oli-import-sync-trp-attr-wrap">
                <table class="widefat striped oli-import-sync-trp-attr-table">
                    <thead class="oli-import-sync-trp-attr-head">
                        <tr><th><?php echo esc_html__( 'Attribute', 'oli-import-sync-for-translatepress' ); ?></th><?php foreach ( $languages as $l ) echo '<th>' . esc_html( $l ) . '</th>'; ?></tr>
                    </thead>
                    <tbody>
                    <?php foreach ( $attributes as $attr ) {
                        echo '<tr><td><strong>' . esc_html( $attr->attribute_label ) . '</strong><br><small class="oli-import-sync-trp-attr-slug">pa_' . esc_html( $attr->attribute_name ) . '</small></td>';
                        foreach ( $languages as $lang ) {
                            echo '<td>';
                            $this->render_copy_field( '_trp_attr_' . $attr->attribute_name . '_' . $lang, true );
                            echo '</td>';
                        }
                        echo '</tr>';
                    } ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; endif; ?>
        <?php endif; // WooCommerce
    }

    /**
     * Logs tab — enable/disable logging and view log
     */
    private function render_tab_logs() {
        $logging_enabled   = get_option( 'oli_import_sync_trp_logging_enabled', 0 );
        $auto_slug_enabled = get_option( 'oli_import_sync_trp_auto_slug_enabled', 1 );
        $log_content     = '';
        if ( file_exists( $this->log_file ) ) {
            $log_content = file_get_contents( $this->log_file );
        }
        $log_size = file_exists( $this->log_file ) ? size_format( filesize( $this->log_file ) ) : '0 B';

        if ( isset( $_GET['cleared'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Log cleared.', 'oli-import-sync-for-translatepress' ) . '</p></div>';
        }
        if ( isset( $_GET['settings-updated'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Settings saved.', 'oli-import-sync-for-translatepress' ) . '</p></div>';
        }
        ?>
        <form method="post" action="options.php">
            <?php settings_fields( 'oli_import_sync_trp_settings' ); ?>
            <table class="form-table">
                <tr>
                    <th scope="row"><?php echo esc_html__( 'Enable Logging', 'oli-import-sync-for-translatepress' ); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="oli_import_sync_trp_logging_enabled" value="1" <?php checked( $logging_enabled, 1 ); ?> />
                            <?php echo esc_html__( 'Write detailed import logs', 'oli-import-sync-for-translatepress' ); ?>
                        </label>
                        <p class="description"><?php echo esc_html__( 'Enable before running an import. Disable when not debugging to save disk space.', 'oli-import-sync-for-translatepress' ); ?></p>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><?php echo esc_html__( 'Auto-derive post slug from translated title', 'oli-import-sync-for-translatepress' ); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="oli_import_sync_trp_auto_slug_enabled" value="1" <?php checked( $auto_slug_enabled, 1 ); ?> />
                            <?php echo wp_kses( __( 'When <code>_trp_slug_[lang]</code> is empty, build the translated post/product slug from <code>_trp_title_[lang]</code>', 'oli-import-sync-for-translatepress' ), array( 'code' => array() ) ); ?>
                        </label>
                        <p class="description"><?php echo esc_html__( 'Requires TranslatePress SEO Pack. Existing translations for the same default slug are replaced on each import.', 'oli-import-sync-for-translatepress' ); ?></p>
                    </td>
                </tr>
            </table>
            <?php submit_button( __( 'Save Settings', 'oli-import-sync-for-translatepress' ) ); ?>
        </form>
        <hr />
        <h2><?php echo esc_html__( 'Import Log', 'oli-import-sync-for-translatepress' ); ?> <small class="oli-import-sync-trp-log-meta">(<?php echo esc_html( $log_size ); ?>)</small></h2>
        <?php if ( ! empty( $log_content ) ) : ?>
            <form method="post" class="oli-import-sync-trp-log-form">
                <?php wp_nonce_field( 'oli_import_sync_trp_clear_log' ); ?>
                <button type="submit" name="oli_import_sync_trp_clear_log" class="button button-secondary oli-import-sync-trp-clear-log"><?php echo esc_html__( 'Clear Log', 'oli-import-sync-for-translatepress' ); ?></button>
            </form>
            <textarea readonly class="oli-import-sync-trp-log"><?php echo esc_textarea( $log_content ); ?></textarea>
        <?php else : ?>
            <p class="oli-import-sync-trp-muted"><?php echo esc_html__( 'No log entries yet. Enable logging above and run an import.', 'oli-import-sync-for-translatepress' ); ?></p>
        <?php endif;
    }
}

new Oli_Import_Sync_For_TranslatePress();

/**
 * =============================================================================
 * HELPER FUNCTIONS (using official TranslatePress Custom API)
 * =============================================================================
 */

/**
 * Manually add a translation (wrapper for official API).
 *
 * @param string $original   The original string.
 * @param string $translated The translated string.
 * @param string $lang       Target language code (e.g., fr_CA, es_ES).
 * @return array|WP_Error
 */
function oli_import_sync_trp_add_translation( $original, $translated, $lang = 'fr_CA' ) {
    if ( ! function_exists( 'trpc_insert_translation' ) ) {
        return new WP_Error( 'missing_api', 'TranslatePress Custom API plugin is required.' );
    }

    return trpc_insert_translation( $original, $translated, $lang, array( 'status' => 2 ) );
}

/**
 * Bulk add translations (wrapper for official API).
 *
 * @param array  $translations Array of 'original' => 'translated' pairs.
 * @param string $lang         Target language code.
 * @return array|WP_Error Results.
 */
function oli_import_sync_trp_bulk_add_translations( $translations, $lang = 'fr_CA' ) {
    if ( ! function_exists( 'trpc_insert_translations_bulk' ) ) {
        return new WP_Error( 'missing_api', 'TranslatePress Custom API plugin is required.' );
    }

    $formatted = array();
    foreach ( $translations as $original => $translated ) {
        $formatted[] = array(
            'original'   => $original,
            'translated' => $translated,
        );
    }

    return trpc_insert_translations_bulk( $formatted, $lang, array( 'status' => 2 ) );
}
