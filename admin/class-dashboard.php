<?php
/**
 * Dashboard admin interface
 *
 * Link health and statistics dashboard
 */

if (!defined('ABSPATH')) {
    exit;
}

class ILM_Dashboard {

    private static $instance = null;

    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('admin_menu', array($this, 'add_menu_page'));
    }

    /**
     * Add dashboard submenu page
     */
    public function add_menu_page() {
        add_submenu_page(
            'internal-linking-manager',
            'Dashboard',
            'Dashboard',
            'manage_options',
            'internal-linking-dashboard',
            array($this, 'render_dashboard')
        );
    }

    /**
     * Render dashboard
     */
    public function render_dashboard() {
        $stats = ILM_Stats::get_instance();
        $site_stats = $stats->get_site_stats();

        ?>
        <div class="wrap">
            <h1>Link Health Dashboard</h1>

            <div class="ilm-dashboard-grid">
                <!-- Summary Cards -->
                <div class="ilm-card ilm-summary-card">
                    <h3>Total Active Links</h3>
                    <div class="ilm-stat-number"><?php echo number_format($site_stats['total_links']); ?></div>
                </div>

                <div class="ilm-card ilm-summary-card">
                    <h3>Target Pages</h3>
                    <div class="ilm-stat-number"><?php echo number_format($site_stats['total_targets']); ?></div>
                </div>

                <div class="ilm-card ilm-summary-card">
                    <h3>Orphan Posts</h3>
                    <div class="ilm-stat-number"><?php echo count($site_stats['orphan_posts']); ?></div>
                    <p class="description">Posts with no internal links</p>
                </div>

                <div class="ilm-card ilm-summary-card">
                    <h3>Over-Optimized</h3>
                    <div class="ilm-stat-number"><?php echo count($site_stats['over_optimized']); ?></div>
                    <p class="description">Targets with overused primary anchors</p>
                </div>
            </div>

            <!-- Over-Optimized Anchors -->
            <?php if (!empty($site_stats['over_optimized'])): ?>
                <div class="ilm-card">
                    <h2>Over-Optimized Anchors</h2>
                    <p class="description">These targets have their primary anchor used too frequently.</p>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th>Target URL</th>
                                <th>Primary Anchor</th>
                                <th>Usage %</th>
                                <th>Total Links</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($site_stats['over_optimized'] as $target): ?>
                                <tr>
                                    <td><?php echo esc_html($target['url']); ?></td>
                                    <td><strong><?php echo esc_html($target['primary_anchor']); ?></strong></td>
                                    <td>
                                        <span class="ilm-badge ilm-badge-warning">
                                            <?php echo esc_html($target['percentage']); ?>%
                                        </span>
                                    </td>
                                    <td><?php echo esc_html($target['total_links']); ?></td>
                                    <td>
                                        <a href="<?php echo admin_url('admin.php?page=internal-linking-manager&action=edit&id=' . $target['target_id']); ?>"
                                            class="button button-small">Edit Variations</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <!-- Under-Linked Targets -->
            <?php if (!empty($site_stats['underlinked_targets'])): ?>
                <div class="ilm-card">
                    <h2>Under-Linked Targets</h2>
                    <p class="description">These targets have fewer than 3 links pointing to them.</p>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th>Target URL</th>
                                <th>Primary Anchor</th>
                                <th>Link Count</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($site_stats['underlinked_targets'] as $target): ?>
                                <tr>
                                    <td><?php echo esc_html($target['url']); ?></td>
                                    <td><?php echo esc_html($target['primary_anchor']); ?></td>
                                    <td>
                                        <span class="ilm-badge ilm-badge-<?php echo $target['link_count'] == 0 ? 'error' : 'warning'; ?>">
                                            <?php echo esc_html($target['link_count']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="<?php echo admin_url('admin.php?page=internal-linking-manager&action=edit&id=' . $target['id']); ?>"
                                            class="button button-small">View</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <!-- Recent Activity -->
            <?php if (!empty($site_stats['recent_activity'])): ?>
                <div class="ilm-card">
                    <h2>Recent Linking Activity</h2>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Post</th>
                                <th>Target URL</th>
                                <th>Anchor Text</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($site_stats['recent_activity'] as $activity): ?>
                                <tr>
                                    <td><?php echo esc_html(date('M j, Y', strtotime($activity['created_at']))); ?></td>
                                    <td>
                                        <a href="<?php echo get_edit_post_link($activity['post_id']); ?>">
                                            <?php echo esc_html($activity['post_title']); ?>
                                        </a>
                                    </td>
                                    <td><?php echo esc_html($activity['url']); ?></td>
                                    <td><code><?php echo esc_html($activity['anchor_text']); ?></code></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <!-- Orphan Posts -->
            <?php if (!empty($site_stats['orphan_posts'])): ?>
                <div class="ilm-card">
                    <h2>Orphan Posts</h2>
                    <p class="description">These published posts have no internal links from this plugin.</p>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th>Post Title</th>
                                <th>Post Type</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (array_slice($site_stats['orphan_posts'], 0, 10) as $post): ?>
                                <tr>
                                    <td>
                                        <a href="<?php echo get_edit_post_link($post['ID']); ?>">
                                            <?php echo esc_html($post['post_title']); ?>
                                        </a>
                                    </td>
                                    <td><?php echo esc_html($post['post_type']); ?></td>
                                    <td>
                                        <a href="<?php echo get_edit_post_link($post['ID']); ?>" class="button button-small">
                                            Edit Post
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php if (count($site_stats['orphan_posts']) > 10): ?>
                        <p style="margin-top: 15px;">
                            <em>Showing 10 of <?php echo count($site_stats['orphan_posts']); ?> orphan posts</em>
                        </p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }
}
