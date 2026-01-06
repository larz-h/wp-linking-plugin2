<?php
/**
 * REST API endpoints
 *
 * Handles API routes for the editor sidebar and admin interface
 */

if (!defined('ABSPATH')) {
    exit;
}

class ILM_REST_API {

    private static $instance = null;
    private $namespace = 'internal-linking/v1';

    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('rest_api_init', array($this, 'register_routes'));
    }

    /**
     * Register REST API routes
     */
    public function register_routes() {
        // Scan post for link opportunities
        register_rest_route($this->namespace, '/scan/(?P<post_id>\d+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'scan_post'),
            'permission_callback' => array($this, 'check_edit_permission'),
            'args' => array(
                'post_id' => array(
                    'required' => true,
                    'validate_callback' => function($param) {
                        return is_numeric($param);
                    }
                )
            )
        ));

        // Insert link
        register_rest_route($this->namespace, '/insert-link', array(
            'methods' => 'POST',
            'callback' => array($this, 'insert_link'),
            'permission_callback' => array($this, 'check_edit_permission'),
            'args' => array(
                'post_id' => array('required' => true),
                'target_id' => array('required' => true),
                'anchor_text' => array('required' => true),
                'target_url' => array('required' => true),
                'occurrence_index' => array('default' => 0)
            )
        ));

        // Remove link
        register_rest_route($this->namespace, '/remove-link', array(
            'methods' => 'POST',
            'callback' => array($this, 'remove_link'),
            'permission_callback' => array($this, 'check_edit_permission'),
            'args' => array(
                'post_id' => array('required' => true),
                'target_id' => array('required' => true),
                'anchor_text' => array('required' => true)
            )
        ));

        // Get target stats
        register_rest_route($this->namespace, '/stats/target/(?P<target_id>\d+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_target_stats'),
            'permission_callback' => array($this, 'check_edit_permission'),
            'args' => array(
                'target_id' => array('required' => true)
            )
        ));

        // Get post stats
        register_rest_route($this->namespace, '/stats/post/(?P<post_id>\d+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_post_stats'),
            'permission_callback' => array($this, 'check_edit_permission'),
            'args' => array(
                'post_id' => array('required' => true)
            )
        ));

        // Get all targets
        register_rest_route($this->namespace, '/targets', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_targets'),
            'permission_callback' => array($this, 'check_edit_permission')
        ));

        // Create target
        register_rest_route($this->namespace, '/targets', array(
            'methods' => 'POST',
            'callback' => array($this, 'create_target'),
            'permission_callback' => array($this, 'check_manage_permission'),
            'args' => array(
                'url' => array('required' => true),
                'primary_anchor' => array('required' => true),
                'anchor_variations' => array('default' => array()),
                'priority' => array('default' => 5),
                'link_type' => array('default' => 'internal')
            )
        ));

        // Update target
        register_rest_route($this->namespace, '/targets/(?P<id>\d+)', array(
            'methods' => 'PUT',
            'callback' => array($this, 'update_target'),
            'permission_callback' => array($this, 'check_manage_permission'),
            'args' => array(
                'id' => array('required' => true)
            )
        ));

        // Delete target
        register_rest_route($this->namespace, '/targets/(?P<id>\d+)', array(
            'methods' => 'DELETE',
            'callback' => array($this, 'delete_target'),
            'permission_callback' => array($this, 'check_manage_permission'),
            'args' => array(
                'id' => array('required' => true)
            )
        ));

        // Get settings
        register_rest_route($this->namespace, '/settings', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_settings'),
            'permission_callback' => array($this, 'check_manage_permission')
        ));

        // Update settings
        register_rest_route($this->namespace, '/settings', array(
            'methods' => 'POST',
            'callback' => array($this, 'update_settings'),
            'permission_callback' => array($this, 'check_manage_permission')
        ));
    }

    /**
     * Scan post for opportunities
     */
    public function scan_post($request) {
        $post_id = $request->get_param('post_id');
        $scanner = ILM_Scanner::get_instance();

        $opportunities = $scanner->scan_gutenberg_blocks($post_id);

        return rest_ensure_response(array(
            'success' => true,
            'opportunities' => $opportunities,
            'post_id' => $post_id
        ));
    }

    /**
     * Insert link
     */
    public function insert_link($request) {
        $post_id = $request->get_param('post_id');
        $target_id = $request->get_param('target_id');
        $anchor_text = $request->get_param('anchor_text');
        $target_url = $request->get_param('target_url');
        $occurrence_index = $request->get_param('occurrence_index');

        $inserter = ILM_Link_Inserter::get_instance();
        $result = $inserter->insert_link($post_id, $target_id, $anchor_text, $target_url, $occurrence_index);

        if ($result['success']) {
            return rest_ensure_response($result);
        } else {
            return new WP_Error('insert_failed', $result['message'], array('status' => 400));
        }
    }

    /**
     * Remove link
     */
    public function remove_link($request) {
        $post_id = $request->get_param('post_id');
        $target_id = $request->get_param('target_id');
        $anchor_text = $request->get_param('anchor_text');

        $inserter = ILM_Link_Inserter::get_instance();
        $result = $inserter->remove_link($post_id, $target_id, $anchor_text);

        return rest_ensure_response($result);
    }

    /**
     * Get target stats
     */
    public function get_target_stats($request) {
        $target_id = $request->get_param('target_id');
        $stats = ILM_Stats::get_instance();

        $data = $stats->get_target_stats($target_id);

        if ($data) {
            return rest_ensure_response($data);
        } else {
            return new WP_Error('not_found', 'Target not found', array('status' => 404));
        }
    }

    /**
     * Get post stats
     */
    public function get_post_stats($request) {
        $post_id = $request->get_param('post_id');
        $stats = ILM_Stats::get_instance();

        $data = $stats->get_post_stats($post_id);

        return rest_ensure_response($data);
    }

    /**
     * Get all targets
     */
    public function get_targets($request) {
        $db = ILM_Database::get_instance();
        $targets = $db->get_targets();

        return rest_ensure_response(array(
            'success' => true,
            'targets' => $targets
        ));
    }

    /**
     * Create target
     */
    public function create_target($request) {
        $db = ILM_Database::get_instance();

        $data = array(
            'url' => sanitize_text_field($request->get_param('url')),
            'primary_anchor' => sanitize_text_field($request->get_param('primary_anchor')),
            'anchor_variations' => $request->get_param('anchor_variations'),
            'cluster_id' => $request->get_param('cluster_id'),
            'priority' => intval($request->get_param('priority')),
            'link_type' => sanitize_text_field($request->get_param('link_type'))
        );

        $target_id = $db->add_target($data);

        if ($target_id) {
            $target = $db->get_target($target_id);
            return rest_ensure_response(array(
                'success' => true,
                'target' => $target
            ));
        } else {
            return new WP_Error('create_failed', 'Failed to create target', array('status' => 400));
        }
    }

    /**
     * Update target
     */
    public function update_target($request) {
        $db = ILM_Database::get_instance();
        $id = $request->get_param('id');

        $data = array();
        if ($request->has_param('url')) {
            $data['url'] = sanitize_text_field($request->get_param('url'));
        }
        if ($request->has_param('primary_anchor')) {
            $data['primary_anchor'] = sanitize_text_field($request->get_param('primary_anchor'));
        }
        if ($request->has_param('anchor_variations')) {
            $data['anchor_variations'] = $request->get_param('anchor_variations');
        }
        if ($request->has_param('cluster_id')) {
            $data['cluster_id'] = $request->get_param('cluster_id');
        }
        if ($request->has_param('priority')) {
            $data['priority'] = intval($request->get_param('priority'));
        }
        if ($request->has_param('link_type')) {
            $data['link_type'] = sanitize_text_field($request->get_param('link_type'));
        }

        $result = $db->update_target($id, $data);

        if ($result) {
            $target = $db->get_target($id);
            return rest_ensure_response(array(
                'success' => true,
                'target' => $target
            ));
        } else {
            return new WP_Error('update_failed', 'Failed to update target', array('status' => 400));
        }
    }

    /**
     * Delete target
     */
    public function delete_target($request) {
        $db = ILM_Database::get_instance();
        $id = $request->get_param('id');

        $result = $db->delete_target($id);

        if ($result) {
            return rest_ensure_response(array(
                'success' => true,
                'message' => 'Target deleted'
            ));
        } else {
            return new WP_Error('delete_failed', 'Failed to delete target', array('status' => 400));
        }
    }

    /**
     * Get settings
     */
    public function get_settings($request) {
        $settings = array(
            'max_links_per_post' => ILM_Database::get_setting('max_links_per_post', 10),
            'max_links_same_url' => ILM_Database::get_setting('max_links_same_url', 1),
            'min_paragraphs_between' => ILM_Database::get_setting('min_paragraphs_between', 0),
            'case_sensitive' => ILM_Database::get_setting('case_sensitive', false),
            'prefer_intra_cluster' => ILM_Database::get_setting('prefer_intra_cluster', false),
            'primary_anchor_threshold' => ILM_Database::get_setting('primary_anchor_threshold', 30)
        );

        return rest_ensure_response($settings);
    }

    /**
     * Update settings
     */
    public function update_settings($request) {
        $settings = array(
            'max_links_per_post',
            'max_links_same_url',
            'min_paragraphs_between',
            'case_sensitive',
            'prefer_intra_cluster',
            'primary_anchor_threshold'
        );

        foreach ($settings as $key) {
            if ($request->has_param($key)) {
                ILM_Database::update_setting($key, $request->get_param($key));
            }
        }

        return rest_ensure_response(array(
            'success' => true,
            'message' => 'Settings updated'
        ));
    }

    /**
     * Check if user can edit posts
     */
    public function check_edit_permission($request) {
        $post_id = $request->get_param('post_id');

        if ($post_id) {
            return current_user_can('edit_post', $post_id);
        }

        return current_user_can('edit_posts');
    }

    /**
     * Check if user can manage options
     */
    public function check_manage_permission($request) {
        return current_user_can('manage_options');
    }
}
