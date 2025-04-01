<?php
if (!defined('ABSPATH')) exit;

$model = new WP_Page_Feedback_Model();

// Handle bulk actions
if (isset($_POST['action']) && isset($_POST['feedback'])) {
    $action = sanitize_text_field($_POST['action']);
    $feedback_ids = array_map('absint', $_POST['feedback']);

    if ($action === 'resolve') {
        foreach ($feedback_ids as $id) {
            $model->update($id, ['status' => 'resolved']);
        }
        echo '<div class="notice notice-success"><p>Selected feedback marked as resolved.</p></div>';
    }
}

// Get feedbacks with pagination
$per_page = 20;
$current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
$offset = ($current_page - 1) * $per_page;

$status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
$where = '';
$params = [];

global $wpdb;
$table_name = $wpdb->prefix . 'page_feedbacks';

// Build query based on filters
if ($status_filter) {
    $total_items = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE status = %s",
            $status_filter
        )
    );
    
    $feedbacks = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT f.*, u.display_name as assigned_name 
             FROM $table_name f 
             LEFT JOIN {$wpdb->users} u ON f.assigned_id = u.ID 
             WHERE f.status = %s 
             ORDER BY f.date_created DESC LIMIT %d OFFSET %d",
            array_merge([$status_filter], [$per_page, $offset])
        )
    );
} else {
    $total_items = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
    
    $feedbacks = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT f.*, u.display_name as assigned_name 
             FROM $table_name f 
             LEFT JOIN {$wpdb->users} u ON f.assigned_id = u.ID 
             ORDER BY f.date_created DESC LIMIT %d OFFSET %d",
            [$per_page, $offset]
        )
    );
}

$total_pages = ceil($total_items / $per_page);
?>

<div class="wrap">
    <h1 class="wp-heading-inline">Page Feedback</h1>

    <form method="get">
        <input type="hidden" name="page" value="wp-page-feedback">
        <select name="status">
            <option value="">All Status</option>
            <option value="pending" <?php selected($status_filter, 'pending'); ?>>Pending</option>
            <option value="in_progress" <?php selected($status_filter, 'in_progress'); ?>>In Progress</option>
            <option value="reviewing" <?php selected($status_filter, 'reviewing'); ?>>Under Review</option>
            <option value="approved" <?php selected($status_filter, 'approved'); ?>>Approved</option>
            <option value="rejected" <?php selected($status_filter, 'rejected'); ?>>Rejected</option>
            <option value="resolved" <?php selected($status_filter, 'resolved'); ?>>Resolved</option>
            <option value="deferred" <?php selected($status_filter, 'deferred'); ?>>Deferred</option>
        </select>
        <input type="submit" class="button" value="Filter">
    </form>

    <form method="post">
        <table class="wp-list-table widefat striped">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Page</th>
                    <th>Comment</th>
                    <th>Action Type</th>
                    <th class="column-status">Status</th>
                    <th>Device Info</th>
                    <th>Author</th>
                    <th>Assigned To</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($feedbacks as $feedback): ?>
                    <tr>
                        <td><?php echo esc_html($feedback->id); ?></td>
                        <td>
                            <?php 
                            $post_title = '';
                            if ($feedback->post_id) {
                                $post = get_post($feedback->post_id);
                                if ($post) {
                                    $post_title = $post->post_title;
                                }
                            }
                            ?>
                            <a href="<?php echo esc_url($feedback->page_url); ?>" target="_blank">
                                <?php 
                                if ($post_title) {
                                    echo esc_html($post_title);
                                } else {
                                    // Fallback to URL path if no post found
                                    echo esc_html(wp_parse_url($feedback->page_url, PHP_URL_PATH) ?: '/');
                                }
                                ?>
                            </a>
                        </td>
                        <td><?php echo esc_html($feedback->comment); ?></td>
                        <td>
                            <span class="wp-pf-action-type <?php echo esc_attr($feedback->action_type); ?>">
                                <?php echo esc_html($feedback->action_type); ?>
                            </span>
                        </td>
                        <td class="column-status">
                            <div class="wp-pf-status-wrapper">
                                <span class="wp-pf-status-icon dashicons dashicons-marker" data-status="<?php echo esc_attr($feedback->status); ?>"></span>
                                <select class="wp-pf-status-select" data-feedback-id="<?php echo esc_attr($feedback->id); ?>" data-status="<?php echo esc_attr($feedback->status); ?>">
                                    <?php 
                                    $status_info = [
                                        'pending' => [
                                            'icon' => 'clock',
                                            'label' => 'Pending',
                                            'class' => 'pending'
                                        ],
                                        'in_progress' => [
                                            'icon' => 'controls-play',
                                            'label' => 'In Progress',
                                            'class' => 'in-progress'
                                        ],
                                        'reviewing' => [
                                            'icon' => 'search',
                                            'label' => 'Under Review',
                                            'class' => 'reviewing'
                                        ],
                                        'approved' => [
                                            'icon' => 'yes-alt',
                                            'label' => 'Approved',
                                            'class' => 'approved'
                                        ],
                                        'rejected' => [
                                            'icon' => 'no-alt',
                                            'label' => 'Rejected',
                                            'class' => 'rejected'
                                        ],
                                        'resolved' => [
                                            'icon' => 'yes-alt',
                                            'label' => 'Resolved',
                                            'class' => 'resolved'
                                        ],
                                        'deferred' => [
                                            'icon' => 'backup',
                                            'label' => 'Deferred',
                                            'class' => 'deferred'
                                        ]
                                    ];
                                    foreach ($status_info as $status => $information): ?>
                                        <option value="<?php echo esc_attr($status); ?>" <?php selected($feedback->status, $status); ?> class="status-<?php echo esc_attr($information['class']); ?>">
                                            <?php echo esc_html($information['label']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </td>
                        <td>
                            <span title="<?php echo esc_attr($feedback->user_agent); ?>" class="wp-pf-device-info">
                                <?php 
                                    echo esc_html($feedback->device_type);
                                    if ($feedback->screen_width && $feedback->screen_height) {
                                        echo ' (' . esc_html($feedback->screen_width . ' x ' . $feedback->screen_height) . ')';
                                    }
                                ?>
                            </span>
                        </td>
                        <td>
                            <?php 
                                $author = get_userdata($feedback->author_id);
                                echo esc_html($author ? $author->display_name : 'Unknown');
                            ?>
                        </td>
                        <td>
                            <?php 
                                if ($feedback->assigned_id) {
                                    $assigned = get_userdata($feedback->assigned_id);
                                    echo esc_html($assigned ? $assigned->display_name : 'Unknown');
                                } else {
                                    echo '—';
                                }
                            ?>
                        </td>
                        <td>
                            <span title="<?php echo esc_attr(get_date_from_gmt($feedback->date_created)); ?>">
                                <?php echo esc_html(human_time_diff(strtotime($feedback->date_created), current_time('timestamp')) . ' ago'); ?>
                            </span>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="tablenav bottom">
            <div class="alignleft actions bulkactions">
                <select name="action">
                    <option value="-1">Bulk Actions</option>
                    <option value="resolve">Mark as Resolved</option>
                </select>
                <input type="submit" class="button action" value="Apply">
            </div>
            <div class="tablenav-pages">
                <?php
                echo paginate_links([
                    'base' => add_query_arg('paged', '%#%'),
                    'format' => '',
                    'prev_text' => __('&laquo;'),
                    'next_text' => __('&raquo;'),
                    'total' => $total_pages,
                    'current' => $current_page
                ]);
                ?>
            </div>
        </div>
    </form>
</div>
