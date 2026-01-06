<?php
/**
 * Target Pages admin interface
 *
 * Manages the Target Pages admin screen
 */

if (!defined('ABSPATH')) {
    exit;
}

class ILM_Target_Pages {

    private static $instance = null;
    private $db;

    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->db = ILM_Database::get_instance();
        add_action('admin_menu', array($this, 'add_menu_page'));
        add_action('admin_post_ilm_save_target', array($this, 'save_target'));
        add_action('admin_post_ilm_delete_target', array($this, 'delete_target'));
    }

    /**
     * Add admin menu page
     */
    public function add_menu_page() {
        add_menu_page(
            'Internal Linking Manager',
            'Link Manager',
            'manage_options',
            'internal-linking-manager',
            array($this, 'render_targets_page'),
            'dashicons-admin-links',
            30
        );

        add_submenu_page(
            'internal-linking-manager',
            'Target Pages',
            'Target Pages',
            'manage_options',
            'internal-linking-manager',
            array($this, 'render_targets_page')
        );
    }

    /**
     * Render targets page
     */
    public function render_targets_page() {
        // Handle edit mode
        $editing = false;
        $target = null;

        if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
            $editing = true;
            $target = $this->db->get_target(intval($_GET['id']));
        }

        $targets = $this->db->get_targets();
        $stats_instance = ILM_Stats::get_instance();

        ?>
        <div class="wrap">
            <h1><?php echo $editing ? 'Edit Target Page' : 'Target Pages'; ?></h1>

            <?php if (isset($_GET['message'])): ?>
                <div class="notice notice-success is-dismissible">
                    <p><?php echo esc_html($this->get_message($_GET['message'])); ?></p>
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['error'])): ?>
                <div class="notice notice-error is-dismissible">
                    <p><?php echo esc_html($_GET['error']); ?></p>
                </div>
            <?php endif; ?>

            <div class="ilm-admin-layout">
                <div class="ilm-main-content">
                    <?php if ($editing): ?>
                        <?php $this->render_edit_form($target); ?>
                    <?php else: ?>
                        <?php $this->render_add_form(); ?>
                        <?php $this->render_targets_table($targets, $stats_instance); ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render add/edit form
     */
    private function render_add_form() {
        ?>
        <div class="ilm-card">
            <h2>Add New Target Page</h2>
            <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                <input type="hidden" name="action" value="ilm_save_target">
                <?php wp_nonce_field('ilm_save_target', 'ilm_nonce'); ?>

                <table class="form-table">
                    <tr>
                        <th><label for="url">Target URL *</label></th>
                        <td>
                            <input type="text" id="url" name="url" class="regular-text" required
                                placeholder="/blog/example-post or https://example.com/page">
                            <p class="description">Internal path or full URL</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="primary_anchor">Primary Anchor Text *</label></th>
                        <td>
                            <input type="text" id="primary_anchor" name="primary_anchor" class="regular-text" required
                                placeholder="best running shoes">
                            <p class="description">Main keyword phrase</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="anchor_variations">Anchor Variations</label></th>
                        <td>
                            <textarea id="anchor_variations" name="anchor_variations" rows="5" class="large-text"
                                placeholder="top running shoes&#10;running shoes for men&#10;best shoes for running"></textarea>
                            <p class="description">One variation per line</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="priority">Priority</label></th>
                        <td>
                            <input type="number" id="priority" name="priority" min="1" max="10" value="5">
                            <p class="description">1-10, used for conflict resolution (higher wins)</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="link_type">Link Type</label></th>
                        <td>
                            <select id="link_type" name="link_type">
                                <option value="internal">Internal</option>
                                <option value="affiliate">Affiliate</option>
                                <option value="external">External</option>
                            </select>
                        </td>
                    </tr>
                </table>

                <p class="submit">
                    <input type="submit" class="button button-primary" value="Add Target Page">
                </p>
            </form>
        </div>
        <?php
    }

    /**
     * Render edit form
     */
    private function render_edit_form($target) {
        if (!$target) {
            echo '<div class="notice notice-error"><p>Target not found.</p></div>';
            return;
        }

        $variations_text = is_array($target['anchor_variations'])
            ? implode("\n", $target['anchor_variations'])
            : $target['anchor_variations'];
        ?>
        <div class="ilm-card">
            <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                <input type="hidden" name="action" value="ilm_save_target">
                <input type="hidden" name="target_id" value="<?php echo esc_attr($target['id']); ?>">
                <?php wp_nonce_field('ilm_save_target', 'ilm_nonce'); ?>

                <table class="form-table">
                    <tr>
                        <th><label for="url">Target URL *</label></th>
                        <td>
                            <input type="text" id="url" name="url" class="regular-text" required
                                value="<?php echo esc_attr($target['url']); ?>">
                        </td>
                    </tr>
                    <tr>
                        <th><label for="primary_anchor">Primary Anchor Text *</label></th>
                        <td>
                            <input type="text" id="primary_anchor" name="primary_anchor" class="regular-text" required
                                value="<?php echo esc_attr($target['primary_anchor']); ?>">
                        </td>
                    </tr>
                    <tr>
                        <th><label for="anchor_variations">Anchor Variations</label></th>
                        <td>
                            <textarea id="anchor_variations" name="anchor_variations" rows="5" class="large-text"><?php echo esc_textarea($variations_text); ?></textarea>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="priority">Priority</label></th>
                        <td>
                            <input type="number" id="priority" name="priority" min="1" max="10"
                                value="<?php echo esc_attr($target['priority']); ?>">
                        </td>
                    </tr>
                    <tr>
                        <th><label for="link_type">Link Type</label></th>
                        <td>
                            <select id="link_type" name="link_type">
                                <option value="internal" <?php selected($target['link_type'], 'internal'); ?>>Internal</option>
                                <option value="affiliate" <?php selected($target['link_type'], 'affiliate'); ?>>Affiliate</option>
                                <option value="external" <?php selected($target['link_type'], 'external'); ?>>External</option>
                            </select>
                        </td>
                    </tr>
                </table>

                <p class="submit">
                    <input type="submit" class="button button-primary" value="Update Target">
                    <a href="<?php echo admin_url('admin.php?page=internal-linking-manager'); ?>" class="button">Cancel</a>
                </p>
            </form>
        </div>
        <?php
    }

    /**
     * Render targets table
     */
    private function render_targets_table($targets, $stats_instance) {
        ?>
        <div class="ilm-card">
            <h2>All Target Pages</h2>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>URL</th>
                        <th>Primary Anchor</th>
                        <th>Variations</th>
                        <th>Priority</th>
                        <th>Links</th>
                        <th>Type</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($targets)): ?>
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 40px;">
                                No target pages yet. Add your first target above!
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($targets as $target): ?>
                            <?php
                            $stats = $this->db->get_target_stats($target['id']);
                            $variation_count = count($target['anchor_variations']);
                            ?>
                            <tr>
                                <td>
                                    <strong><?php echo esc_html($target['url']); ?></strong>
                                </td>
                                <td><?php echo esc_html($target['primary_anchor']); ?></td>
                                <td><?php echo esc_html($variation_count); ?> variations</td>
                                <td><?php echo esc_html($target['priority']); ?></td>
                                <td>
                                    <strong><?php echo esc_html($stats['total']); ?></strong> links
                                    <?php if ($stats['total'] > 0): ?>
                                        <br>
                                        <small style="color: #666;">
                                            <?php
                                            $primary_count = 0;
                                            foreach ($stats['by_anchor'] as $anchor_stat) {
                                                if ($anchor_stat['anchor_text'] === $target['primary_anchor']) {
                                                    $primary_count = $anchor_stat['count'];
                                                    break;
                                                }
                                            }
                                            $percentage = $stats['total'] > 0 ? round(($primary_count / $stats['total']) * 100) : 0;
                                            echo esc_html($percentage) . '% primary';
                                            ?>
                                        </small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="ilm-badge ilm-badge-<?php echo esc_attr($target['link_type']); ?>">
                                        <?php echo esc_html(ucfirst($target['link_type'])); ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="<?php echo admin_url('admin.php?page=internal-linking-manager&action=edit&id=' . $target['id']); ?>"
                                        class="button button-small">Edit</a>
                                    <a href="<?php echo wp_nonce_url(admin_url('admin-post.php?action=ilm_delete_target&id=' . $target['id']), 'ilm_delete_target'); ?>"
                                        class="button button-small"
                                        onclick="return confirm('Are you sure you want to delete this target? This will not remove existing links.');">Delete</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    /**
     * Save target
     */
    public function save_target() {
        if (!isset($_POST['ilm_nonce']) || !wp_verify_nonce($_POST['ilm_nonce'], 'ilm_save_target')) {
            wp_die('Security check failed');
        }

        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }

        $url = sanitize_text_field($_POST['url']);
        $primary_anchor = sanitize_text_field($_POST['primary_anchor']);
        $anchor_variations = sanitize_textarea_field($_POST['anchor_variations']);
        $priority = isset($_POST['priority']) ? intval($_POST['priority']) : 5;
        $link_type = isset($_POST['link_type']) ? sanitize_text_field($_POST['link_type']) : 'internal';

        // Convert variations to array
        $variations = array_filter(array_map('trim', explode("\n", $anchor_variations)));

        $data = array(
            'url' => $url,
            'primary_anchor' => $primary_anchor,
            'anchor_variations' => $variations,
            'priority' => $priority,
            'link_type' => $link_type
        );

        if (isset($_POST['target_id'])) {
            // Update existing
            $target_id = intval($_POST['target_id']);
            $result = $this->db->update_target($target_id, $data);
            $message = $result ? 'updated' : 'error';
        } else {
            // Create new
            $result = $this->db->add_target($data);
            $message = $result ? 'created' : 'error';
        }

        wp_redirect(admin_url('admin.php?page=internal-linking-manager&message=' . $message));
        exit;
    }

    /**
     * Delete target
     */
    public function delete_target() {
        if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'ilm_delete_target')) {
            wp_die('Security check failed');
        }

        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }

        $id = intval($_GET['id']);
        $result = $this->db->delete_target($id);

        $message = $result ? 'deleted' : 'error';
        wp_redirect(admin_url('admin.php?page=internal-linking-manager&message=' . $message));
        exit;
    }

    /**
     * Get message text
     */
    private function get_message($code) {
        $messages = array(
            'created' => 'Target page created successfully.',
            'updated' => 'Target page updated successfully.',
            'deleted' => 'Target page deleted successfully.',
            'error' => 'An error occurred. Please try again.'
        );

        return isset($messages[$code]) ? $messages[$code] : '';
    }
}
