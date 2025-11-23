<?php
/**
 * Plugin Name: Google Gemini File Search for WooCommerce
 * Plugin URI: https://ablancodev.com
 * Description: Búsqueda semántica de productos WooCommerce usando Google Gemini File Search Store
 * Version: 1.0.0
 * Author: ablancodev
 * Author URI: https://ablancodev.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: google-file-search
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 8.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('GFS_VERSION', '1.0.0');
define('GFS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('GFS_PLUGIN_URL', plugin_dir_url(__FILE__));
define('GFS_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Main plugin class
 */
class Google_File_Search {

    private static $instance = null;

    /**
     * Get singleton instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        $this->includes();
        $this->init_hooks();
    }

    /**
     * Include required files
     */
    private function includes() {
        require_once GFS_PLUGIN_DIR . 'includes/class-gemini-client.php';
        require_once GFS_PLUGIN_DIR . 'includes/class-product-sync.php';
        require_once GFS_PLUGIN_DIR . 'includes/class-search-api.php';
        require_once GFS_PLUGIN_DIR . 'includes/class-admin.php';
        require_once GFS_PLUGIN_DIR . 'includes/class-frontend.php';
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        add_action('plugins_loaded', array($this, 'check_dependencies'));
        add_action('init', array($this, 'init'));
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }

    /**
     * Check if WooCommerce is active
     */
    public function check_dependencies() {
        if (!class_exists('WooCommerce')) {
            add_action('admin_notices', array($this, 'woocommerce_missing_notice'));
            deactivate_plugins(GFS_PLUGIN_BASENAME);
            return false;
        }
        return true;
    }

    /**
     * WooCommerce missing notice
     */
    public function woocommerce_missing_notice() {
        ?>
        <div class="notice notice-error">
            <p><?php _e('Google Gemini File Search requiere WooCommerce para funcionar. Por favor instala y activa WooCommerce.', 'google-file-search'); ?></p>
        </div>
        <?php
    }

    /**
     * Initialize plugin components
     */
    public function init() {
        if (!$this->check_dependencies()) {
            return;
        }

        GFS_Product_Sync::get_instance();
        GFS_Search_API::get_instance();
        GFS_Admin::get_instance();
        GFS_Frontend::get_instance();

        load_plugin_textdomain('google-file-search', false, dirname(GFS_PLUGIN_BASENAME) . '/languages');
    }

    /**
     * Plugin activation
     */
    public function activate() {
        // Create options with default values
        add_option('gfs_gemini_api_key', '');
        add_option('gfs_corpus_id', '');
        add_option('gfs_auto_sync', 'yes');
        add_option('gfs_sync_on_save', 'yes');

        // Create custom table for sync tracking
        global $wpdb;
        $table_name = $wpdb->prefix . 'gfs_sync_log';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            product_id bigint(20) NOT NULL,
            document_id varchar(255) NOT NULL,
            sync_status varchar(20) NOT NULL,
            sync_date datetime DEFAULT CURRENT_TIMESTAMP,
            error_message text,
            PRIMARY KEY (id),
            KEY product_id (product_id),
            KEY document_id (document_id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);

        flush_rewrite_rules();
    }

    /**
     * Plugin deactivation
     */
    public function deactivate() {
        flush_rewrite_rules();
    }
}

/**
 * Initialize the plugin
 */
function gfs_init() {
    return Google_File_Search::get_instance();
}

// Start the plugin
gfs_init();
