<?php
/**
 * Settings admin interface
 *
 * Manages plugin settings
 */

if (!defined('ABSPATH')) {
    exit;
}

class ILM_Settings {

    private static $instance = null;

    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('admin_menu', array($this, 'add_menu_page'));
        add_action('admin_post_ilm_save_settings', array($this, 'save_settings'));
    }

    /**
     * Add settings submenu page
     */
    public function add_menu_page() {
        add_submenu_page(
            'internal-linking-manager',
            'Settings',
            'Settings',
            'manage_options',
            'internal-linking-settings',
            array($this, 'render_settings_page')
        );
    }

    /**
     * Render settings page
     */
    public function render_settings_page() {
        $settings = array(
            'max_links_per_post' => ILM_Database::get_setting('max_links_per_post', 10),
            'max_links_same_url' => ILM_Database::get_setting('max_links_same_url', 1),
            'min_paragraphs_between' => ILM_Database::get_setting('min_paragraphs_between', 0),
            'case_sensitive' => ILM_Database::get_setting('case_sensitive', false),
            'prefer_intra_cluster' => ILM_Database::get_setting('prefer_intra_cluster', false),
            'primary_anchor_threshold' => ILM_Database::get_setting('primary_anchor_threshold', 30)
        );

        ?>
        <div class="wrap">
            <h1>Internal Linking Manager Settings</h1>

            <?php if (isset($_GET['message']) && $_GET['message'] === 'saved'): ?>
                <div class="notice notice-success is-dismissible">
                    <p>Settings saved successfully.</p>
                </div>
            <?php endif; ?>

            <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                <input type="hidden" name="action" value="ilm_save_settings">
                <?php wp_nonce_field('ilm_save_settings', 'ilm_settings_nonce'); ?>

                <div class="ilm-card">
                    <h2>Link Density Controls</h2>
                    <table class="form-table">
                        <tr>
                            <th><label for="max_links_per_post">Max Links Per Post</label></th>
                            <td>
                                <input type="number" id="max_links_per_post" name="max_links_per_post"
                                    min="1" max="100" value="<?php echo esc_attr($settings['max_links_per_post']); ?>">
                                <p class="description">Maximum number of internal links per post</p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="max_links_same_url">Max Links to Same URL</label></th>
                            <td>
                                <input type="number" id="max_links_same_url" name="max_links_same_url"
                                    min="1" max="10" value="<?php echo esc_attr($settings['max_links_same_url']); ?>">
                                <p class="description">Maximum links to the same target URL per post</p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="min_paragraphs_between">Minimum Paragraphs Between Links</label></th>
                            <td>
                                <input type="number" id="min_paragraphs_between" name="min_paragraphs_between"
                                    min="0" max="10" value="<?php echo esc_attr($settings['min_paragraphs_between']); ?>">
                                <p class="description">Minimum paragraphs between links (0 = no minimum)</p>
                            </td>
                        </tr>
                    </table>
                </div>

                <div class="ilm-card">
                    <h2>Matching Options</h2>
                    <table class="form-table">
                        <tr>
                            <th><label for="case_sensitive">Case Sensitivity</label></th>
                            <td>
                                <label>
                                    <input type="checkbox" id="case_sensitive" name="case_sensitive" value="1"
                                        <?php checked($settings['case_sensitive'], true); ?>>
                                    Require exact case matching
                                </label>
                                <p class="description">When enabled, "Running Shoes" will not match "running shoes"</p>
                            </td>
                        </tr>
                    </table>
                </div>

                <div class="ilm-card">
                    <h2>Anchor Text Optimization</h2>
                    <table class="form-table">
                        <tr>
                            <th><label for="primary_anchor_threshold">Primary Anchor Warning Threshold</label></th>
                            <td>
                                <input type="number" id="primary_anchor_threshold" name="primary_anchor_threshold"
                                    min="10" max="100" value="<?php echo esc_attr($settings['primary_anchor_threshold']); ?>">
                                <span>%</span>
                                <p class="description">Warn when primary anchor is used more than this percentage</p>
                            </td>
                        </tr>
                    </table>
                </div>

                <div class="ilm-card">
                    <h2>Cluster Options</h2>
                    <table class="form-table">
                        <tr>
                            <th><label for="prefer_intra_cluster">Prefer Intra-Cluster Links</label></th>
                            <td>
                                <label>
                                    <input type="checkbox" id="prefer_intra_cluster" name="prefer_intra_cluster" value="1"
                                        <?php checked($settings['prefer_intra_cluster'], true); ?>>
                                    Prioritize links within the same topic cluster
                                </label>
                                <p class="description">When enabled, targets in the same cluster get higher priority (Phase 2 feature)</p>
                            </td>
                        </tr>
                    </table>
                </div>

                <p class="submit">
                    <input type="submit" class="button button-primary" value="Save Settings">
                </p>
            </form>
        </div>
        <?php
    }

    /**
     * Save settings
     */
    public function save_settings() {
        if (!isset($_POST['ilm_settings_nonce']) || !wp_verify_nonce($_POST['ilm_settings_nonce'], 'ilm_save_settings')) {
            wp_die('Security check failed');
        }

        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }

        $settings = array(
            'max_links_per_post' => isset($_POST['max_links_per_post']) ? intval($_POST['max_links_per_post']) : 10,
            'max_links_same_url' => isset($_POST['max_links_same_url']) ? intval($_POST['max_links_same_url']) : 1,
            'min_paragraphs_between' => isset($_POST['min_paragraphs_between']) ? intval($_POST['min_paragraphs_between']) : 0,
            'case_sensitive' => isset($_POST['case_sensitive']) ? true : false,
            'prefer_intra_cluster' => isset($_POST['prefer_intra_cluster']) ? true : false,
            'primary_anchor_threshold' => isset($_POST['primary_anchor_threshold']) ? intval($_POST['primary_anchor_threshold']) : 30
        );

        foreach ($settings as $key => $value) {
            ILM_Database::update_setting($key, $value);
        }

        wp_redirect(admin_url('admin.php?page=internal-linking-settings&message=saved'));
        exit;
    }
}
