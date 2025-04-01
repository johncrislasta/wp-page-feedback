<?php

class WP_Page_Feedback_Notifications {
    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {}

    /**
     * Send notification when new feedback is created
     */
    public function notify_new_feedback($feedback_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'page_feedbacks';
        
        $feedback = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table_name} WHERE id = %d",
            $feedback_id
        ));

        if (!$feedback) {
            return;
        }

        // Get the assigned user's email if one is assigned
        $assigned_user_email = '';
        if ($feedback->assigned_id) {
            $assigned_user = get_userdata($feedback->assigned_id);
            if ($assigned_user) {
                $assigned_user_email = $assigned_user->user_email;
            }
        }

        // Get admin email
        $admin_email = get_option('admin_email');

        // Set up email content
        $subject = sprintf('[%s] New Feedback Submitted', get_bloginfo('name'));
        
        $message = sprintf(
            "New feedback has been submitted on %s\n\n" .
            "Page: %s\n" .
            "Action Type: %s\n" .
            "Comment: %s\n" .
            "Status: %s\n\n" .
            "View in admin: %s",
            get_bloginfo('name'),
            $feedback->page_url,
            ucfirst($feedback->action_type),
            $feedback->comment,
            ucfirst($feedback->status),
            admin_url('admin.php?page=wp-page-feedback')
        );

        $headers = array('Content-Type: text/plain; charset=UTF-8');
        
        // Send to admin
        wp_mail($admin_email, $subject, $message, $headers);

        // Send to assigned user if different from admin
        if ($assigned_user_email && $assigned_user_email !== $admin_email) {
            wp_mail($assigned_user_email, $subject, $message, $headers);
        }
    }

    /**
     * Send notification when feedback status changes
     */
    public function notify_status_change($feedback_id, $old_status, $new_status) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'page_feedbacks';
        
        $feedback = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table_name} WHERE id = %d",
            $feedback_id
        ));

        if (!$feedback) {
            return;
        }

        // Get the assigned user's email if one is assigned
        $assigned_user_email = '';
        if ($feedback->assigned_id) {
            $assigned_user = get_userdata($feedback->assigned_id);
            if ($assigned_user) {
                $assigned_user_email = $assigned_user->user_email;
            }
        }

        // Get admin email
        $admin_email = get_option('admin_email');

        // Set up email content
        $subject = sprintf('[%s] Feedback Status Updated', get_bloginfo('name'));
        
        $message = sprintf(
            "A feedback status has been updated on %s\n\n" .
            "Page: %s\n" .
            "Action Type: %s\n" .
            "Comment: %s\n" .
            "Status changed from: %s to %s\n\n" .
            "View in admin: %s",
            get_bloginfo('name'),
            $feedback->page_url,
            ucfirst($feedback->action_type),
            $feedback->comment,
            ucfirst($old_status),
            ucfirst($new_status),
            admin_url('admin.php?page=wp-page-feedback')
        );

        $headers = array('Content-Type: text/plain; charset=UTF-8');
        
        // Send to admin
        wp_mail($admin_email, $subject, $message, $headers);

        // Send to assigned user if different from admin
        if ($assigned_user_email && $assigned_user_email !== $admin_email) {
            wp_mail($assigned_user_email, $subject, $message, $headers);
        }
    }
}
