<?php
/**
 * Link insertion class
 *
 * Handles inserting links into post content
 */

if (!defined('ABSPATH')) {
    exit;
}

class ILM_Link_Inserter {

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
     * Insert link into Gutenberg content
     *
     * @param int $post_id Post ID
     * @param int $target_id Target page ID
     * @param string $anchor_text Anchor text to link
     * @param string $target_url Target URL
     * @param int $occurrence_index Which occurrence to link (0-based)
     * @return array Result with updated content
     */
    public function insert_link($post_id, $target_id, $anchor_text, $target_url, $occurrence_index = 0) {
        $post = get_post($post_id);
        if (!$post) {
            return array(
                'success' => false,
                'message' => 'Post not found'
            );
        }

        // Check link limits
        $limit_check = $this->check_link_limits($post_id, $target_id);
        if (!$limit_check['allowed']) {
            return array(
                'success' => false,
                'message' => $limit_check['message']
            );
        }

        $content = $post->post_content;

        // Check if this is a Gutenberg post
        if (has_blocks($content)) {
            $result = $this->insert_link_in_blocks($content, $anchor_text, $target_url, $occurrence_index);
        } else {
            $result = $this->insert_link_in_html($content, $anchor_text, $target_url, $occurrence_index);
        }

        if ($result['success']) {
            // Update post content
            wp_update_post(array(
                'ID' => $post_id,
                'post_content' => $result['content']
            ));

            // Log the link
            $this->db->log_link($post_id, $target_id, $anchor_text, 'plugin');

            return array(
                'success' => true,
                'content' => $result['content'],
                'message' => 'Link inserted successfully'
            );
        }

        return $result;
    }

    /**
     * Insert link into Gutenberg blocks
     *
     * @param string $content Block content
     * @param string $anchor_text Text to link
     * @param string $target_url Target URL
     * @param int $occurrence_index Which occurrence
     * @return array Result
     */
    private function insert_link_in_blocks($content, $anchor_text, $target_url, $occurrence_index) {
        $blocks = parse_blocks($content);
        $current_occurrence = 0;
        $link_inserted = false;

        $updated_blocks = $this->process_blocks_for_link(
            $blocks,
            $anchor_text,
            $target_url,
            $occurrence_index,
            $current_occurrence,
            $link_inserted
        );

        if ($link_inserted) {
            $updated_content = serialize_blocks($updated_blocks);
            return array(
                'success' => true,
                'content' => $updated_content
            );
        }

        return array(
            'success' => false,
            'message' => 'Could not find text to link'
        );
    }

    /**
     * Process blocks recursively to insert link
     *
     * @param array $blocks Blocks array
     * @param string $anchor_text Text to link
     * @param string $target_url Target URL
     * @param int $target_occurrence Target occurrence index
     * @param int $current_occurrence Current occurrence counter (by reference)
     * @param bool $link_inserted Link inserted flag (by reference)
     * @return array Updated blocks
     */
    private function process_blocks_for_link($blocks, $anchor_text, $target_url, $target_occurrence, &$current_occurrence, &$link_inserted) {
        foreach ($blocks as &$block) {
            if ($link_inserted) {
                break;
            }

            // Only process linkable blocks
            if (in_array($block['blockName'], array('core/paragraph', 'core/list', 'core/list-item'))) {
                if (!empty($block['innerHTML'])) {
                    $result = $this->insert_link_in_html_once(
                        $block['innerHTML'],
                        $anchor_text,
                        $target_url,
                        $target_occurrence,
                        $current_occurrence
                    );

                    if ($result['found']) {
                        $block['innerHTML'] = $result['html'];
                        $block['innerContent'][0] = $result['html'];

                        if ($result['inserted']) {
                            $link_inserted = true;
                            break;
                        }
                    }
                }
            }

            // Process inner blocks recursively
            if (!empty($block['innerBlocks'])) {
                $block['innerBlocks'] = $this->process_blocks_for_link(
                    $block['innerBlocks'],
                    $anchor_text,
                    $target_url,
                    $target_occurrence,
                    $current_occurrence,
                    $link_inserted
                );
            }
        }

        return $blocks;
    }

    /**
     * Insert link in HTML content
     *
     * @param string $html HTML content
     * @param string $anchor_text Text to link
     * @param string $target_url Target URL
     * @param int $occurrence_index Which occurrence
     * @return array Result
     */
    private function insert_link_in_html($html, $anchor_text, $target_url, $occurrence_index) {
        $current_occurrence = 0;

        $result = $this->insert_link_in_html_once(
            $html,
            $anchor_text,
            $target_url,
            $occurrence_index,
            $current_occurrence
        );

        if ($result['inserted']) {
            return array(
                'success' => true,
                'content' => $result['html']
            );
        }

        return array(
            'success' => false,
            'message' => 'Could not find text to link'
        );
    }

    /**
     * Insert link in HTML once
     *
     * @param string $html HTML content
     * @param string $anchor_text Text to link
     * @param string $target_url Target URL
     * @param int $target_occurrence Target occurrence
     * @param int $current_occurrence Current occurrence (by reference)
     * @return array Result with updated HTML
     */
    private function insert_link_in_html_once($html, $anchor_text, $target_url, $target_occurrence, &$current_occurrence) {
        // Skip if already has link to this URL
        if (stripos($html, 'href="' . $target_url . '"') !== false) {
            return array(
                'found' => false,
                'inserted' => false,
                'html' => $html
            );
        }

        // Pattern to find the text, avoiding existing links, headings, code
        $pattern = preg_quote($anchor_text, '/');
        $pattern = '/(?<!<a[^>]*>)(?<!<h[1-6][^>]*>)(?<!<code[^>]*>)\b(' . $pattern . ')\b(?![^<]*<\/a>)(?![^<]*<\/h[1-6]>)(?![^<]*<\/code>)/iu';

        $found = false;
        $inserted = false;

        $updated_html = preg_replace_callback($pattern, function($matches) use ($target_url, $target_occurrence, &$current_occurrence, &$found, &$inserted) {
            $found = true;

            if ($current_occurrence === $target_occurrence) {
                $inserted = true;
                $current_occurrence++;
                return '<a href="' . esc_url($target_url) . '">' . $matches[1] . '</a>';
            }

            $current_occurrence++;
            return $matches[0];
        }, $html, -1, $count);

        return array(
            'found' => $found,
            'inserted' => $inserted,
            'html' => $updated_html
        );
    }

    /**
     * Check if link insertion is allowed
     *
     * @param int $post_id Post ID
     * @param int $target_id Target ID
     * @return array Result
     */
    private function check_link_limits($post_id, $target_id) {
        $existing_links = $this->db->get_post_links($post_id);

        // Check max links per post
        $max_links = ILM_Database::get_setting('max_links_per_post', 10);
        if (count($existing_links) >= $max_links) {
            return array(
                'allowed' => false,
                'message' => sprintf('Maximum links per post reached (%d)', $max_links)
            );
        }

        // Check max links to same URL
        $max_same_url = ILM_Database::get_setting('max_links_same_url', 1);
        $same_url_count = 0;
        foreach ($existing_links as $link) {
            if ($link['target_id'] == $target_id) {
                $same_url_count++;
            }
        }

        if ($same_url_count >= $max_same_url) {
            return array(
                'allowed' => false,
                'message' => sprintf('Maximum links to this URL reached (%d)', $max_same_url)
            );
        }

        return array('allowed' => true);
    }

    /**
     * Remove link from content
     *
     * @param int $post_id Post ID
     * @param int $target_id Target ID
     * @param string $anchor_text Anchor text
     * @return array Result
     */
    public function remove_link($post_id, $target_id, $anchor_text) {
        $post = get_post($post_id);
        if (!$post) {
            return array(
                'success' => false,
                'message' => 'Post not found'
            );
        }

        // Mark as removed in database
        $this->db->mark_link_removed($post_id, $target_id, $anchor_text);

        return array(
            'success' => true,
            'message' => 'Link marked as removed'
        );
    }
}
