<?php
/**
 * Content scanner class
 *
 * Handles scanning post content for linkable phrases
 */

if (!defined('ABSPATH')) {
    exit;
}

class ILM_Scanner {

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
     * Scan post content for link opportunities
     *
     * @param int $post_id Post ID
     * @param string $content Post content
     * @return array Link opportunities
     */
    public function scan_content($post_id, $content) {
        // Get all targets
        $targets = $this->db->get_targets();

        if (empty($targets)) {
            return array();
        }

        // Get existing links for this post
        $existing_links = $this->db->get_post_links($post_id);
        $existing_target_ids = array_column($existing_links, 'target_id');

        // Get settings
        $max_same_url = ILM_Database::get_setting('max_links_same_url', 1);
        $case_sensitive = ILM_Database::get_setting('case_sensitive', false);

        // Count existing links per target
        $link_counts = array_count_values($existing_target_ids);

        // Parse content into searchable text
        $parsed_content = $this->parse_content($content);

        $opportunities = array();

        foreach ($targets as $target) {
            // Skip if already linked max times
            $current_count = isset($link_counts[$target['id']]) ? $link_counts[$target['id']] : 0;
            if ($current_count >= $max_same_url) {
                continue;
            }

            // Build list of all possible anchors
            $anchors = array_merge(
                array($target['primary_anchor']),
                $target['anchor_variations']
            );

            foreach ($anchors as $anchor) {
                if (empty($anchor)) {
                    continue;
                }

                $matches = $this->find_matches($parsed_content, $anchor, $case_sensitive);

                if (!empty($matches)) {
                    // Get usage stats for this anchor
                    $stats = $this->db->get_target_stats($target['id']);

                    $opportunities[] = array(
                        'target_id' => $target['id'],
                        'target_url' => $target['url'],
                        'target_primary' => $target['primary_anchor'],
                        'anchor_text' => $anchor,
                        'is_primary' => ($anchor === $target['primary_anchor']),
                        'matches' => $matches,
                        'stats' => $stats,
                        'priority' => $target['priority'],
                        'already_linked' => in_array($target['id'], $existing_target_ids)
                    );
                }
            }
        }

        // Sort by priority (higher first)
        usort($opportunities, function($a, $b) {
            return $b['priority'] - $a['priority'];
        });

        return $opportunities;
    }

    /**
     * Parse content into searchable format
     *
     * @param string $content Raw content
     * @return string Parsed content
     */
    private function parse_content($content) {
        // Remove existing links
        $content = preg_replace('/<a\s+[^>]*>.*?<\/a>/i', '', $content);

        // Remove headings
        $content = preg_replace('/<h[1-6][^>]*>.*?<\/h[1-6]>/i', '', $content);

        // Remove code blocks
        $content = preg_replace('/<code[^>]*>.*?<\/code>/is', '', $content);
        $content = preg_replace('/<pre[^>]*>.*?<\/pre>/is', '', $content);

        // Remove script and style tags
        $content = preg_replace('/<script[^>]*>.*?<\/script>/is', '', $content);
        $content = preg_replace('/<style[^>]*>.*?<\/style>/is', '', $content);

        // Strip remaining HTML tags but keep the text
        $content = strip_tags($content);

        return $content;
    }

    /**
     * Find all matches of an anchor phrase in content
     *
     * @param string $content Content to search
     * @param string $anchor Anchor phrase
     * @param bool $case_sensitive Case sensitivity
     * @return array Matches with positions
     */
    private function find_matches($content, $anchor, $case_sensitive = false) {
        $matches = array();

        // Escape special regex characters
        $pattern = preg_quote($anchor, '/');

        // Add word boundaries
        $pattern = '\b' . $pattern . '\b';

        // Set case sensitivity
        $flags = $case_sensitive ? '' : 'i';

        // Find all matches with positions
        if (preg_match_all('/' . $pattern . '/u' . $flags, $content, $match_results, PREG_OFFSET_CAPTURE)) {
            foreach ($match_results[0] as $match) {
                $matched_text = $match[0];
                $position = $match[1];

                // Get context around match (50 chars before and after)
                $context_start = max(0, $position - 50);
                $context_end = min(strlen($content), $position + strlen($matched_text) + 50);
                $context = substr($content, $context_start, $context_end - $context_start);

                // Add ellipsis if truncated
                if ($context_start > 0) {
                    $context = '...' . $context;
                }
                if ($context_end < strlen($content)) {
                    $context = $context . '...';
                }

                $matches[] = array(
                    'text' => $matched_text,
                    'position' => $position,
                    'context' => $context
                );
            }
        }

        return $matches;
    }

    /**
     * Find matches in Gutenberg blocks
     *
     * @param int $post_id Post ID
     * @return array Link opportunities
     */
    public function scan_gutenberg_blocks($post_id) {
        $post = get_post($post_id);
        if (!$post) {
            return array();
        }

        // Check if this is a Gutenberg post
        if (!has_blocks($post->post_content)) {
            // Fall back to regular content scanning
            return $this->scan_content($post_id, $post->post_content);
        }

        // Parse blocks
        $blocks = parse_blocks($post->post_content);

        // Extract text content from paragraph blocks only
        $text_content = $this->extract_text_from_blocks($blocks);

        // Scan the extracted text
        return $this->scan_content($post_id, $text_content);
    }

    /**
     * Extract text from Gutenberg blocks
     *
     * @param array $blocks Parsed blocks
     * @return string Extracted text
     */
    private function extract_text_from_blocks($blocks) {
        $text = '';

        foreach ($blocks as $block) {
            // Only process paragraph and list blocks
            if (in_array($block['blockName'], array('core/paragraph', 'core/list', 'core/list-item'))) {
                if (!empty($block['innerHTML'])) {
                    $text .= $block['innerHTML'] . "\n\n";
                }
            }

            // Recursively process inner blocks
            if (!empty($block['innerBlocks'])) {
                $text .= $this->extract_text_from_blocks($block['innerBlocks']);
            }
        }

        return $text;
    }

    /**
     * Get block information for a specific position in content
     *
     * @param string $content Post content
     * @param int $position Character position
     * @return array Block info
     */
    public function get_block_at_position($content, $position) {
        if (!has_blocks($content)) {
            return null;
        }

        $blocks = parse_blocks($content);
        $current_position = 0;

        return $this->find_block_by_position($blocks, $position, $current_position);
    }

    /**
     * Find block by position (recursive)
     *
     * @param array $blocks Blocks array
     * @param int $target_position Target position
     * @param int $current_position Current position tracker
     * @return array|null Block info
     */
    private function find_block_by_position($blocks, $target_position, &$current_position) {
        foreach ($blocks as $index => $block) {
            $block_content = serialize_block($block);
            $block_length = strlen($block_content);
            $block_end = $current_position + $block_length;

            if ($target_position >= $current_position && $target_position < $block_end) {
                // Found the block
                return array(
                    'block' => $block,
                    'index' => $index,
                    'start' => $current_position,
                    'end' => $block_end
                );
            }

            $current_position = $block_end;

            // Check inner blocks
            if (!empty($block['innerBlocks'])) {
                $inner_result = $this->find_block_by_position(
                    $block['innerBlocks'],
                    $target_position,
                    $current_position
                );
                if ($inner_result) {
                    return $inner_result;
                }
            }
        }

        return null;
    }
}
