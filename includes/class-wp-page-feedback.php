<?php

class WP_Page_Feedback {
    private $model;
    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->model = new WP_Page_Feedback_Model();
        $this->init_hooks();
    }

    private function init_hooks() {
        // Frontend hooks
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('wp_ajax_wp_page_feedback_save', [$this, 'ajax_save_feedback']);
        add_action('wp_ajax_wp_page_feedback_get', [$this, 'ajax_get_feedback']);
        add_action('wp_ajax_wp_page_feedback_get_users', [$this, 'ajax_get_users']);
        
        // Admin hooks
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_action('wp_ajax_wp_page_feedback_mark_resolved', [$this, 'ajax_mark_resolved']);
        add_action('wp_ajax_wp_page_feedback_update_status', [$this, 'ajax_update_status']);
    }

    public function enqueue_assets() {
        if (!current_user_can('edit_pages')) {
            return;
        }

        wp_enqueue_script(
            'wp-pf-js',
            plugin_dir_url(WP_PF_PLUGIN_FILE) . 'assets/feedback.js',
            ['jquery'],
            WP_PF_VERSION,
            true
        );

        wp_localize_script('wp-pf-js', 'WP_PF_AJAX', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wp_pf_nonce'),
            'post_id' => get_queried_object_id()
        ]);

        wp_enqueue_style(
            'wp-pf-feedback-css',
            plugin_dir_url(WP_PF_PLUGIN_FILE) . 'assets/feedback.css',
            [],
            WP_PF_VERSION
        );
    }

    public function enqueue_admin_assets($hook) {
        $screen = get_current_screen();
        if ($screen->id !== 'toplevel_page_wp-page-feedback') {
            return;
        }

        wp_enqueue_style(
            'wp-pf-admin-css',
            plugin_dir_url(WP_PF_PLUGIN_FILE) . 'assets/admin-feedback.css',
            [],
            WP_PF_VERSION
        );

        wp_enqueue_script(
            'wp-pf-admin-js',
            plugin_dir_url(WP_PF_PLUGIN_FILE) . 'assets/admin-feedback.js',
            ['jquery'],
            WP_PF_VERSION,
            true
        );

        wp_localize_script('wp-pf-admin-js', 'wpPageFeedback', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wp_page_feedback_nonce')
        ]);

        wp_enqueue_style(
            'wp-pf-admin-css',
            plugin_dir_url(WP_PF_PLUGIN_FILE) . 'assets/admin-feedback.css',
            [],
            WP_PF_VERSION
        );
    }

    public function add_admin_menu() {
        $unresolved_count = $this->model->count_unresolved();
        $menu_title = 'Feedback';
        if ($unresolved_count > 0) {
            $menu_title .= " <span class='awaiting-mod count-{$unresolved_count}'><span class='pending-count'>{$unresolved_count}</span></span>";
        }

        add_menu_page(
            'Page Feedback',
            $menu_title,
            'edit_pages',
            'wp-page-feedback',
            [$this, 'render_admin_page'],
            'dashicons-feedback',
            25
        );
    }

    public function render_admin_page() {
        include_once WP_PF_PATH . 'admin/feedback-list.php';
    }

    public function ajax_save_feedback() {
        check_ajax_referer('wp_pf_nonce');

        if (!current_user_can('edit_pages')) {
            wp_send_json_error('Permission denied');
        }

        $data = [
            'page_url' => sanitize_text_field($_POST['page_url']),
            'post_id' => !empty($_POST['post_id']) ? intval($_POST['post_id']) : null,
            'selector' => sanitize_text_field($_POST['selector']),
            'comment' => sanitize_textarea_field($_POST['comment']),
            'action_type' => sanitize_text_field($_POST['action_type']),
            'device_type' => sanitize_text_field($_POST['device_type']),
            'screen_width' => intval($_POST['screen_width']),
            'screen_height' => intval($_POST['screen_height']),
            'user_agent' => sanitize_text_field($_POST['user_agent']),
            'assigned_id' => !empty($_POST['assigned_id']) ? intval($_POST['assigned_id']) : null,
            'author_id' => get_current_user_id()
        ];

        // If no post_id was provided, try to find it from the URL
        if (empty($data['post_id'])) {
            $url_path = parse_url($data['page_url'], PHP_URL_PATH);
            if ($url_path) {
                // Remove trailing slashes and get the slug
                $slug = trim($url_path, '/');
                if ($slug) {
                    // Try to find post by path
                    $page = get_page_by_path($slug);
                    if ($page) {
                        $data['post_id'] = $page->ID;
                    }
                } else {
                    // This might be the homepage
                    $data['post_id'] = get_option('page_on_front');
                }
            }
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'page_feedbacks';
        
        $result = $wpdb->insert(
            $table_name,
            $data,
            ['%s', '%d', '%s', '%s', '%s',
             '%s', '%d', '%d', '%s', '%d', '%d']
        );
        
        if ($result === false) {
            wp_send_json_error([
                'message' => 'Failed to save feedback',
                'error' => $wpdb->last_error,
                'details' => 'Please try again or contact the administrator if the problem persists.'
            ]);
        }

        $feedback_id = $wpdb->insert_id;

        // Send notification for new feedback
        WP_Page_Feedback_Notifications::get_instance()->notify_new_feedback($feedback_id);

        wp_send_json_success(['id' => $feedback_id]);
    }

    public function ajax_get_feedback() {
        check_ajax_referer('wp_pf_nonce');

        if (!current_user_can('edit_pages')) {
            wp_send_json_error('Permission denied');
        }

        $page_url = esc_url_raw($_POST['page_url']);
        $parsed = wp_parse_url($page_url);
        $clean_url = isset($parsed['scheme']) && isset($parsed['host']) 
            ? $parsed['scheme'] . '://' . $parsed['host'] . (isset($parsed['path']) ? $parsed['path'] : '')
            : $page_url;
        
        $feedbacks = $this->model->get_by_page_url($clean_url, 'pending');
        $results = array_map(function($fb) {
            return [
                'selector' => $fb->selector,
                'comment' => $fb->comment,
                'action_type' => $fb->action_type
            ];
        }, $feedbacks);

        wp_send_json_success($results);
    }

    public function ajax_get_users() {
        check_ajax_referer('wp_pf_nonce');

        if (!current_user_can('edit_pages')) {
            wp_send_json_error('Permission denied');
        }

        $users = get_users([
            'role__in' => ['administrator', 'editor'],
            'orderby' => 'display_name',
            'order' => 'ASC',
            'fields' => ['ID', 'display_name']
        ]);

        wp_send_json_success($users);
    }

    public function ajax_mark_resolved() {
        check_ajax_referer('wp_page_feedback_nonce');

        if (!current_user_can('edit_pages')) {
            wp_send_json_error('Permission denied');
        }

        $feedback_id = absint($_POST['feedback_id']);
        $success = $this->model->update($feedback_id, ['status' => 'resolved']);
        
        if ($success) {
            wp_send_json_success('Marked as resolved');
        } else {
            wp_send_json_error('Failed to mark as resolved');
        }
    }
    
    public function ajax_update_status() {
        if (!check_ajax_referer('wp_page_feedback_nonce', '_ajax_nonce', false)) {
            wp_send_json_error('Invalid nonce');
        }
        
        if (!current_user_can('edit_pages')) {
            wp_send_json_error('Permission denied');
        }
        
        if (!isset($_POST['feedback_id']) || !isset($_POST['status'])) {
            wp_send_json_error('Missing required fields');
        }
        
        $feedback_id = intval($_POST['feedback_id']);
        $new_status = sanitize_text_field($_POST['status']);
        
        // Get current status before update
        $feedback = $this->model->get($feedback_id);
        if (!$feedback) {
            wp_send_json_error('Feedback not found');
        }
        $old_status = $feedback->status;

        // Validate status
        $valid_statuses = ['pending', 'in_progress', 'reviewing', 'approved', 'rejected', 'resolved', 'deferred'];
        if (!in_array($new_status, $valid_statuses)) {
            wp_send_json_error('Invalid status');
        }

        $result = $this->model->update($feedback_id, ['status' => $new_status]);

        if ($result === false) {
            // The model handles the error logging if $wpdb->last_error is set
            wp_send_json_error([
                'message' => 'Failed to update status',
                'error' => $this->model->get_last_error(),
                'details' => 'Please try again or contact the administrator if the problem persists.'
            ]);
        }
        
        // Send notification for status change
        if ($old_status !== $new_status) {
            WP_Page_Feedback_Notifications::get_instance()->notify_status_change($feedback_id, $old_status, $new_status);
        }

        wp_send_json_success(['message' => 'Status updated successfully']);
    }
}
