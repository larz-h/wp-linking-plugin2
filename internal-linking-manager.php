<?php
/**
 * Plugin Name: Internal Linking Manager
 * Plugin URI: https://github.com/yourusername/wp-linking-plugin
 * Description: A comprehensive link management tool for building natural, varied internal links while tracking anchor text usage
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://yourwebsite.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: internal-linking-manager
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('ILM_VERSION', '1.0.0');
define('ILM_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('ILM_PLUGIN_URL', plugin_dir_url(__FILE__));
define('ILM_PLUGIN_FILE', __FILE__);

/**
 * Main plugin class
 */
class Internal_Linking_Manager {

    /**
     * Single instance of the class
     */
    private static $instance = null;

    /**
     * Get singleton instance
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        $this->load_dependencies();
        $this->init_hooks();
    }

    /**
     * Load required files
     */
    private function load_dependencies() {
        // Core classes
        require_once ILM_PLUGIN_DIR . 'includes/class-database.php';
        require_once ILM_PLUGIN_DIR . 'includes/class-scanner.php';
        require_once ILM_PLUGIN_DIR . 'includes/class-link-inserter.php';
        require_once ILM_PLUGIN_DIR . 'includes/class-stats.php';
        require_once ILM_PLUGIN_DIR . 'includes/class-rest-api.php';

        // Admin classes
        if (is_admin()) {
            require_once ILM_PLUGIN_DIR . 'admin/class-target-pages.php';
            require_once ILM_PLUGIN_DIR . 'admin/class-settings.php';
            require_once ILM_PLUGIN_DIR . 'admin/class-dashboard.php';
        }
    }

    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));

        add_action('plugins_loaded', array($this, 'init'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        add_action('enqueue_block_editor_assets', array($this, 'enqueue_editor_assets'));
    }

    /**
     * Initialize plugin
     */
    public function init() {
        // Initialize database
        ILM_Database::get_instance();

        // Initialize REST API
        ILM_REST_API::get_instance();

        // Initialize admin interfaces
        if (is_admin()) {
            ILM_Target_Pages::get_instance();
            ILM_Settings::get_instance();
            ILM_Dashboard::get_instance();
        }
    }

    /**
     * Plugin activation
     */
    public function activate() {
        ILM_Database::create_tables();
        ILM_Database::set_default_settings();
        flush_rewrite_rules();
    }

    /**
     * Plugin deactivation
     */
    public function deactivate() {
        flush_rewrite_rules();
    }

    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook) {
        // Only load on our admin pages
        if (strpos($hook, 'internal-linking') === false) {
            return;
        }

        wp_enqueue_style(
            'ilm-admin',
            ILM_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            ILM_VERSION
        );

        wp_enqueue_script(
            'ilm-admin',
            ILM_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery'),
            ILM_VERSION,
            true
        );

        wp_localize_script('ilm-admin', 'ilmAdmin', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ilm_admin_nonce'),
            'restUrl' => rest_url('internal-linking/v1'),
            'restNonce' => wp_create_nonce('wp_rest')
        ));
    }

    /**
     * Enqueue block editor assets
     */
    public function enqueue_editor_assets() {
        wp_enqueue_script(
            'ilm-editor-sidebar',
            ILM_PLUGIN_URL . 'assets/js/editor-sidebar.js',
            array('wp-plugins', 'wp-edit-post', 'wp-element', 'wp-components', 'wp-data', 'wp-compose'),
            ILM_VERSION,
            true
        );

        wp_enqueue_style(
            'ilm-editor',
            ILM_PLUGIN_URL . 'assets/css/editor.css',
            array('wp-edit-post'),
            ILM_VERSION
        );

        wp_localize_script('ilm-editor-sidebar', 'ilmEditor', array(
            'restUrl' => rest_url('internal-linking/v1'),
            'restNonce' => wp_create_nonce('wp_rest'),
            'postId' => get_the_ID()
        ));
    }
}

// Initialize the plugin
function ilm_init() {
    return Internal_Linking_Manager::get_instance();
}

// Start the plugin
ilm_init();
