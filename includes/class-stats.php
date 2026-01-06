<?php
/**
 * Statistics tracking class
 *
 * Handles usage statistics and analytics
 */

if (!defined('ABSPATH')) {
    exit;
}

class ILM_Stats {

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
    }

    /**
     * Get comprehensive stats for a target
     *
     * @param int $target_id Target ID
     * @return array Stats
     */
    public function get_target_stats($target_id) {
        $target = $this->db->get_target($target_id);
        if (!$target) {
            return null;
        }

        $usage = $this->db->get_target_stats($target_id);

        // Calculate anchor distribution
        $distribution = array();
        $primary_count = 0;
        $variation_counts = array();

        foreach ($usage['by_anchor'] as $anchor_stat) {
            $anchor = $anchor_stat['anchor_text'];
            $count = intval($anchor_stat['count']);

            if ($anchor === $target['primary_anchor']) {
                $primary_count = $count;
            } else {
                $variation_counts[$anchor] = $count;
            }
        }

        // Calculate percentages
        if ($usage['total'] > 0) {
            $primary_percentage = round(($primary_count / $usage['total']) * 100, 1);
        } else {
            $primary_percentage = 0;
        }

        // Check for warnings
        $warnings = array();
        $threshold = ILM_Database::get_setting('primary_anchor_threshold', 30);

        if ($primary_percentage > $threshold) {
            $warnings[] = array(
                'type' => 'overused_primary',
                'message' => sprintf('Primary anchor used %d%% of the time (threshold: %d%%)', $primary_percentage, $threshold)
            );
        }

        if (count($target['anchor_variations']) < 3) {
            $warnings[] = array(
                'type' => 'few_variations',
                'message' => sprintf('Only %d anchor variations defined. Consider adding more.', count($target['anchor_variations']))
            );
        }

        // Find unused variations
        $used_anchors = array_column($usage['by_anchor'], 'anchor_text');
        $unused_variations = array_diff($target['anchor_variations'], $used_anchors);

        if (!empty($unused_variations)) {
            $warnings[] = array(
                'type' => 'unused_variations',
                'message' => sprintf('%d anchor variations never used', count($unused_variations)),
                'variations' => array_values($unused_variations)
            );
        }

        return array(
            'target' => $target,
            'total_links' => $usage['total'],
            'primary_count' => $primary_count,
            'primary_percentage' => $primary_percentage,
            'variation_counts' => $variation_counts,
            'anchor_distribution' => $usage['by_anchor'],
            'warnings' => $warnings,
            'unused_variations' => array_values($unused_variations)
        );
    }

    /**
     * Get site-wide statistics
     *
     * @return array Stats
     */
    public function get_site_stats() {
        global $wpdb;

        $logs_table = $this->db->get_logs_table();
        $targets_table = $this->db->get_targets_table();

        // Total active links
        $total_links = $wpdb->get_var(
            "SELECT COUNT(*) FROM $logs_table WHERE status = 'active'"
        );

        // Total targets
        $total_targets = $wpdb->get_var(
            "SELECT COUNT(*) FROM $targets_table"
        );

        // Under-linked targets (less than 3 links)
        $underlinked = $wpdb->get_results(
            "SELECT t.id, t.url, t.primary_anchor, COUNT(l.id) as link_count
            FROM $targets_table t
            LEFT JOIN $logs_table l ON t.id = l.target_id AND l.status = 'active'
            GROUP BY t.id
            HAVING link_count < 3
            ORDER BY link_count ASC
            LIMIT 10",
            ARRAY_A
        );

        // Over-optimized anchors
        $over_optimized = array();
        $targets = $this->db->get_targets();
        $threshold = ILM_Database::get_setting('primary_anchor_threshold', 30);

        foreach ($targets as $target) {
            $stats = $this->db->get_target_stats($target['id']);
            if ($stats['total'] > 0) {
                $primary_count = 0;
                foreach ($stats['by_anchor'] as $anchor_stat) {
                    if ($anchor_stat['anchor_text'] === $target['primary_anchor']) {
                        $primary_count = intval($anchor_stat['count']);
                        break;
                    }
                }

                $percentage = ($primary_count / $stats['total']) * 100;
                if ($percentage > $threshold) {
                    $over_optimized[] = array(
                        'target_id' => $target['id'],
                        'url' => $target['url'],
                        'primary_anchor' => $target['primary_anchor'],
                        'percentage' => round($percentage, 1),
                        'total_links' => $stats['total']
                    );
                }
            }
        }

        // Recent activity (last 10 links)
        $recent_activity = $wpdb->get_results(
            "SELECT l.*, t.url, t.primary_anchor, p.post_title
            FROM $logs_table l
            LEFT JOIN $targets_table t ON l.target_id = t.id
            LEFT JOIN {$wpdb->posts} p ON l.post_id = p.ID
            WHERE l.status = 'active'
            ORDER BY l.created_at DESC
            LIMIT 10",
            ARRAY_A
        );

        // Posts with no internal links
        $orphan_posts = $wpdb->get_results(
            "SELECT p.ID, p.post_title, p.post_type
            FROM {$wpdb->posts} p
            LEFT JOIN $logs_table l ON p.ID = l.post_id AND l.status = 'active'
            WHERE p.post_status = 'publish'
            AND p.post_type IN ('post', 'page')
            AND l.id IS NULL
            LIMIT 20",
            ARRAY_A
        );

        return array(
            'total_links' => intval($total_links),
            'total_targets' => intval($total_targets),
            'underlinked_targets' => $underlinked,
            'over_optimized' => $over_optimized,
            'recent_activity' => $recent_activity,
            'orphan_posts' => $orphan_posts
        );
    }

    /**
     * Get stats for a specific post
     *
     * @param int $post_id Post ID
     * @return array Stats
     */
    public function get_post_stats($post_id) {
        $links = $this->db->get_post_links($post_id);

        // Group by target
        $by_target = array();
        foreach ($links as $link) {
            $target_id = $link['target_id'];
            if (!isset($by_target[$target_id])) {
                $by_target[$target_id] = array(
                    'url' => $link['url'],
                    'primary_anchor' => $link['primary_anchor'],
                    'anchors' => array()
                );
            }
            $by_target[$target_id]['anchors'][] = $link['anchor_text'];
        }

        return array(
            'total_links' => count($links),
            'unique_targets' => count($by_target),
            'links' => $links,
            'by_target' => $by_target
        );
    }

    /**
     * Calculate anchor diversity score (0-100)
     *
     * @param int $target_id Target ID
     * @return float Score
     */
    public function calculate_diversity_score($target_id) {
        $stats = $this->db->get_target_stats($target_id);

        if ($stats['total'] === 0) {
            return 0;
        }

        // Calculate using Shannon entropy
        $entropy = 0;
        foreach ($stats['by_anchor'] as $anchor_stat) {
            $probability = $anchor_stat['count'] / $stats['total'];
            $entropy -= $probability * log($probability, 2);
        }

        // Normalize to 0-100 scale
        // Max entropy for n anchors is log2(n)
        $max_entropy = log(count($stats['by_anchor']), 2);
        if ($max_entropy > 0) {
            $score = ($entropy / $max_entropy) * 100;
        } else {
            $score = 0;
        }

        return round($score, 1);
    }

    /**
     * Get recommended anchor for a target
     *
     * @param int $target_id Target ID
     * @return array Recommendation
     */
    public function get_recommended_anchor($target_id) {
        $target = $this->db->get_target($target_id);
        if (!$target) {
            return null;
        }

        $stats = $this->db->get_target_stats($target_id);
        $all_anchors = array_merge(
            array($target['primary_anchor']),
            $target['anchor_variations']
        );

        // Count usage for each anchor
        $usage_counts = array();
        foreach ($all_anchors as $anchor) {
            $usage_counts[$anchor] = 0;
        }

        foreach ($stats['by_anchor'] as $anchor_stat) {
            $usage_counts[$anchor_stat['anchor_text']] = intval($anchor_stat['count']);
        }

        // Sort by usage (ascending) to recommend least used
        asort($usage_counts);

        $recommended = array_key_first($usage_counts);
        $count = $usage_counts[$recommended];

        $reason = '';
        if ($count === 0) {
            $reason = 'Never used before';
        } else {
            $reason = sprintf('Least used (%d times)', $count);
        }

        return array(
            'anchor' => $recommended,
            'count' => $count,
            'reason' => $reason,
            'is_primary' => ($recommended === $target['primary_anchor'])
        );
    }
}
