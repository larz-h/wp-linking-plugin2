<?php
/**
 * Database management class
 *
 * Handles database table creation, updates, and queries
 */

if (!defined('ABSPATH')) {
    exit;
}

class ILM_Database {

    private static $instance = null;
    private $wpdb;
    private $targets_table;
    private $logs_table;
    private $settings_table;

    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->targets_table = $wpdb->prefix . 'ilm_targets';
        $this->logs_table = $wpdb->prefix . 'ilm_logs';
        $this->settings_table = $wpdb->prefix . 'ilm_settings';

        // Check and run migrations if needed
        add_action('admin_init', array($this, 'check_database_version'));
    }

    /**
     * Check database version and run migrations if needed
     */
    public function check_database_version() {
        $current_version = get_option('ilm_db_version', '0');

        if (version_compare($current_version, ILM_VERSION, '<')) {
            // Database needs updating
            self::create_tables();
        }
    }

    /**
     * Create database tables
     */
    public static function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        $targets_table = $wpdb->prefix . 'ilm_targets';
        $logs_table = $wpdb->prefix . 'ilm_logs';
        $settings_table = $wpdb->prefix . 'ilm_settings';

        // Target pages table
        $sql_targets = "CREATE TABLE IF NOT EXISTS $targets_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            url varchar(500) NOT NULL,
            post_title varchar(255) DEFAULT NULL,
            primary_anchor varchar(255) NOT NULL,
            anchor_variations text,
            cluster_id bigint(20) unsigned DEFAULT NULL,
            priority int(11) DEFAULT 5,
            link_type varchar(20) DEFAULT 'internal',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY url (url(191)),
            KEY cluster_id (cluster_id),
            KEY priority (priority)
        ) $charset_collate;";

        // Link logs table
        $sql_logs = "CREATE TABLE IF NOT EXISTS $logs_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            post_id bigint(20) unsigned NOT NULL,
            target_id bigint(20) unsigned NOT NULL,
            anchor_text varchar(255) NOT NULL,
            insertion_method varchar(20) DEFAULT 'plugin',
            status varchar(20) DEFAULT 'active',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            removed_at datetime DEFAULT NULL,
            PRIMARY KEY (id),
            KEY post_id (post_id),
            KEY target_id (target_id),
            KEY status (status),
            KEY created_at (created_at)
        ) $charset_collate;";

        // Settings table
        $sql_settings = "CREATE TABLE IF NOT EXISTS $settings_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            setting_key varchar(100) NOT NULL,
            setting_value longtext,
            PRIMARY KEY (id),
            UNIQUE KEY setting_key (setting_key)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_targets);
        dbDelta($sql_logs);
        dbDelta($sql_settings);

        // Store database version
        update_option('ilm_db_version', ILM_VERSION);
    }

    /**
     * Set default settings
     */
    public static function set_default_settings() {
        $defaults = array(
            'max_links_per_post' => 10,
            'max_links_same_url' => 1,
            'min_paragraphs_between' => 0,
            'case_sensitive' => false,
            'prefer_intra_cluster' => false,
            'primary_anchor_threshold' => 30
        );

        foreach ($defaults as $key => $value) {
            self::update_setting($key, $value, true);
        }
    }

    /**
     * Get setting value
     */
    public static function get_setting($key, $default = null) {
        global $wpdb;
        $table = $wpdb->prefix . 'ilm_settings';

        $value = $wpdb->get_var($wpdb->prepare(
            "SELECT setting_value FROM $table WHERE setting_key = %s",
            $key
        ));

        if ($value === null) {
            return $default;
        }

        return maybe_unserialize($value);
    }

    /**
     * Update setting value
     */
    public static function update_setting($key, $value, $skip_if_exists = false) {
        global $wpdb;
        $table = $wpdb->prefix . 'ilm_settings';

        if ($skip_if_exists) {
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $table WHERE setting_key = %s",
                $key
            ));

            if ($exists) {
                return true;
            }
        }

        $serialized_value = maybe_serialize($value);

        return $wpdb->replace(
            $table,
            array(
                'setting_key' => $key,
                'setting_value' => $serialized_value
            ),
            array('%s', '%s')
        );
    }

    /**
     * Add target page
     */
    public function add_target($data) {
        $defaults = array(
            'url' => '',
            'post_title' => '',
            'primary_anchor' => '',
            'anchor_variations' => array(),
            'cluster_id' => null,
            'priority' => 5,
            'link_type' => 'internal'
        );

        $data = wp_parse_args($data, $defaults);

        if (empty($data['url']) || empty($data['primary_anchor'])) {
            error_log('ILM: Empty URL or primary anchor');
            return false;
        }

        $variations = $data['anchor_variations'];
        if (is_array($variations)) {
            $variations = implode("\n", $variations);
        }

        $result = $this->wpdb->insert(
            $this->targets_table,
            array(
                'url' => $data['url'],
                'post_title' => $data['post_title'],
                'primary_anchor' => $data['primary_anchor'],
                'anchor_variations' => $variations,
                'cluster_id' => $data['cluster_id'],
                'priority' => $data['priority'],
                'link_type' => $data['link_type']
            ),
            array('%s', '%s', '%s', '%s', '%d', '%d', '%s')
        );

        if ($result === false) {
            error_log('ILM Database Error: ' . $this->wpdb->last_error);
            error_log('ILM Insert failed for URL: ' . $data['url']);
        }

        return $result ? $this->wpdb->insert_id : false;
    }

    /**
     * Update target page
     */
    public function update_target($id, $data) {
        if (isset($data['anchor_variations']) && is_array($data['anchor_variations'])) {
            $data['anchor_variations'] = implode("\n", $data['anchor_variations']);
        }

        unset($data['id']);
        unset($data['created_at']);
        unset($data['updated_at']);

        $result = $this->wpdb->update(
            $this->targets_table,
            $data,
            array('id' => $id),
            null,
            array('%d')
        );

        return $result !== false;
    }

    /**
     * Delete target page
     */
    public function delete_target($id) {
        return $this->wpdb->delete(
            $this->targets_table,
            array('id' => $id),
            array('%d')
        );
    }

    /**
     * Get target by ID
     */
    public function get_target($id) {
        $target = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM {$this->targets_table} WHERE id = %d",
            $id
        ), ARRAY_A);

        if ($target) {
            $target['anchor_variations'] = $this->parse_variations($target['anchor_variations']);
        }

        return $target;
    }

    /**
     * Get all targets
     */
    public function get_targets($args = array()) {
        $defaults = array(
            'orderby' => 'priority',
            'order' => 'DESC',
            'cluster_id' => null
        );

        $args = wp_parse_args($args, $defaults);

        $sql = "SELECT * FROM {$this->targets_table}";
        $where = array();

        if ($args['cluster_id'] !== null) {
            $where[] = $this->wpdb->prepare('cluster_id = %d', $args['cluster_id']);
        }

        if (!empty($where)) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= $this->wpdb->prepare(
            ' ORDER BY %1s %1s',
            $args['orderby'],
            $args['order']
        );

        $targets = $this->wpdb->get_results($sql, ARRAY_A);

        foreach ($targets as &$target) {
            $target['anchor_variations'] = $this->parse_variations($target['anchor_variations']);
        }

        return $targets;
    }

    /**
     * Log link insertion
     */
    public function log_link($post_id, $target_id, $anchor_text, $method = 'plugin') {
        return $this->wpdb->insert(
            $this->logs_table,
            array(
                'post_id' => $post_id,
                'target_id' => $target_id,
                'anchor_text' => $anchor_text,
                'insertion_method' => $method,
                'status' => 'active'
            ),
            array('%d', '%d', '%s', '%s', '%s')
        );
    }

    /**
     * Mark link as removed
     */
    public function mark_link_removed($post_id, $target_id, $anchor_text) {
        return $this->wpdb->update(
            $this->logs_table,
            array(
                'status' => 'removed',
                'removed_at' => current_time('mysql')
            ),
            array(
                'post_id' => $post_id,
                'target_id' => $target_id,
                'anchor_text' => $anchor_text,
                'status' => 'active'
            ),
            array('%s', '%s'),
            array('%d', '%d', '%s', '%s')
        );
    }

    /**
     * Get links for post
     */
    public function get_post_links($post_id) {
        return $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT l.*, t.url, t.primary_anchor
            FROM {$this->logs_table} l
            LEFT JOIN {$this->targets_table} t ON l.target_id = t.id
            WHERE l.post_id = %d AND l.status = 'active'
            ORDER BY l.created_at DESC",
            $post_id
        ), ARRAY_A);
    }

    /**
     * Get usage stats for target
     */
    public function get_target_stats($target_id) {
        $total = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->logs_table}
            WHERE target_id = %d AND status = 'active'",
            $target_id
        ));

        $by_anchor = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT anchor_text, COUNT(*) as count
            FROM {$this->logs_table}
            WHERE target_id = %d AND status = 'active'
            GROUP BY anchor_text
            ORDER BY count DESC",
            $target_id
        ), ARRAY_A);

        return array(
            'total' => intval($total),
            'by_anchor' => $by_anchor
        );
    }

    /**
     * Parse variations string to array
     */
    private function parse_variations($variations) {
        if (empty($variations)) {
            return array();
        }

        if (is_array($variations)) {
            return $variations;
        }

        return array_filter(array_map('trim', explode("\n", $variations)));
    }

    /**
     * Import targets from CSV
     *
     * @param string $csv_content CSV content
     * @return array Result with counts
     */
    public function import_targets_from_csv($csv_content) {
        $imported = 0;
        $skipped = 0;
        $errors = array();

        // Normalize line endings (handle Windows \r\n, Mac \r, Unix \n)
        $csv_content = str_replace(array("\r\n", "\r"), "\n", $csv_content);

        // Parse CSV
        $lines = explode("\n", $csv_content);

        foreach ($lines as $line_num => $line) {
            $line = trim($line);

            // Skip empty lines and comment lines
            if (empty($line) || substr($line, 0, 1) === '#') {
                continue;
            }

            // Split by pipe delimiter manually (str_getcsv can be problematic with pipes)
            $fields = explode('|', $line);

            if (count($fields) < 3) {
                $errors[] = sprintf(
                    "Line %d skipped: Not enough columns (found %d, need at least 3)",
                    $line_num + 1,
                    count($fields)
                );
                $skipped++;
                continue;
            }

            $url = trim($fields[0]);
            $post_title = isset($fields[1]) ? trim($fields[1]) : '';
            $primary_anchor = isset($fields[2]) ? trim($fields[2]) : '';
            $variations_str = isset($fields[3]) ? trim($fields[3]) : '';

            // Parse variations (comma-separated)
            $variations = array();
            if (!empty($variations_str)) {
                $variations = array_filter(array_map('trim', explode(',', $variations_str)));
            }

            // Validate required fields
            if (empty($url) || empty($primary_anchor)) {
                $errors[] = sprintf(
                    "Line %d skipped: Missing required field (URL: '%s', Primary: '%s')",
                    $line_num + 1,
                    $url,
                    $primary_anchor
                );
                $skipped++;
                continue;
            }

            // Add target
            $result = $this->add_target(array(
                'url' => $url,
                'post_title' => $post_title,
                'primary_anchor' => $primary_anchor,
                'anchor_variations' => $variations,
                'priority' => 5,
                'link_type' => 'internal'
            ));

            if ($result) {
                $imported++;
            } else {
                $db_error = !empty($this->wpdb->last_error) ? $this->wpdb->last_error : 'Unknown error';
                $errors[] = sprintf(
                    "Line %d failed: %s (URL: '%s')",
                    $line_num + 1,
                    $db_error,
                    $url
                );
                $skipped++;
            }
        }

        return array(
            'imported' => $imported,
            'skipped' => $skipped,
            'errors' => $errors
        );
    }

    /**
     * Export targets to CSV
     *
     * @return string CSV content
     */
    public function export_targets_to_csv() {
        $targets = $this->get_targets();

        $csv_lines = array();

        // Add header row for clarity
        $csv_lines[] = '# URL | Post Title | Primary Anchor | Variations (comma-separated)';

        if (empty($targets)) {
            // Add example row if no targets exist
            $csv_lines[] = '/example-post|Example Post Title|example keyword|variation one, variation two, variation three';
        } else {
            foreach ($targets as $target) {
                $variations = is_array($target['anchor_variations'])
                    ? $target['anchor_variations']
                    : $this->parse_variations($target['anchor_variations']);

                $variations_str = implode(', ', $variations);

                // Build CSV line with pipe delimiter
                $csv_lines[] = sprintf(
                    '%s|%s|%s|%s',
                    $this->escape_csv_field($target['url']),
                    $this->escape_csv_field($target['post_title']),
                    $this->escape_csv_field($target['primary_anchor']),
                    $this->escape_csv_field($variations_str)
                );
            }
        }

        return implode("\n", $csv_lines);
    }

    /**
     * Escape CSV field
     *
     * @param string $field Field value
     * @return string Escaped field
     */
    private function escape_csv_field($field) {
        // If field contains pipe, comma, or newline, wrap in quotes
        if (strpos($field, '|') !== false || strpos($field, ',') !== false || strpos($field, "\n") !== false) {
            return '"' . str_replace('"', '""', $field) . '"';
        }
        return $field;
    }

    /**
     * Get table names
     */
    public function get_targets_table() {
        return $this->targets_table;
    }

    public function get_logs_table() {
        return $this->logs_table;
    }

    public function get_settings_table() {
        return $this->settings_table;
    }
}
