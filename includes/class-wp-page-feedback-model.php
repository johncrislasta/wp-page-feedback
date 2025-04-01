<?php

class WP_Page_Feedback_Model {
    private $wpdb;
    private $table_name;

    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table_name = $wpdb->prefix . 'page_feedbacks';
    }

    public function create($data) {
        $defaults = [
            'title' => '',
            'comment' => '',
            'selector' => '',
            'page_url' => '',
            'post_id' => null,
            'author_id' => get_current_user_id(),
            'assigned_id' => null,
            'status' => 'pending',
            'action_type' => 'other'
        ];

        $data = wp_parse_args($data, $defaults);
        
        return $this->wpdb->insert(
            $this->table_name,
            [
                'title' => sanitize_text_field($data['title']),
                'comment' => sanitize_textarea_field($data['comment']),
                'selector' => sanitize_text_field($data['selector']),
                'page_url' => esc_url_raw($data['page_url']),
                'post_id' => $data['post_id'] ? absint($data['post_id']) : null,
                'author_id' => absint($data['author_id']),
                'assigned_id' => $data['assigned_id'] ? absint($data['assigned_id']) : null,
                'status' => sanitize_text_field($data['status']),
                'action_type' => sanitize_text_field($data['action_type'])
            ],
            ['%s', '%s', '%s', '%s', '%d', '%d', '%d', '%s', '%s']
        );
    }

    public function get($id) {
        return $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table_name} WHERE id = %d",
                $id
            )
        );
    }

    public function get_by_page_url($page_url, $status = null) {
        $sql = "SELECT * FROM {$this->table_name} WHERE page_url = %s";
        $params = [$page_url];

        if ($status !== null) {
            $sql .= " AND status = %s";
            $params[] = $status;
        }

        return $this->wpdb->get_results(
            $this->wpdb->prepare($sql, $params)
        );
    }

    public function update($id, $data) {
        $allowed_fields = [
            'title' => '%s',
            'comment' => '%s',
            'selector' => '%s',
            'page_url' => '%s',
            'post_id' => '%d',
            'assigned_id' => '%d',
            'status' => '%s',
            'action_type' => '%s'
        ];

        $update_data = [];
        $format = [];

        foreach ($allowed_fields as $field => $placeholder) {
            if (isset($data[$field])) {
                $update_data[$field] = $data[$field];
                $format[] = $placeholder;
            }
        }

        if (empty($update_data)) {
            return false;
        }

        return $this->wpdb->update(
            $this->table_name,
            $update_data,
            ['id' => $id],
            $format,
            ['%d']
        );
    }

    public function delete($id) {
        return $this->wpdb->delete(
            $this->table_name,
            ['id' => $id],
            ['%d']
        );
    }

    public function get_paged_feedbacks($args = []) {
        $defaults = [
            'per_page' => 20,
            'current_page' => 1,
            'status' => '',
            'orderby' => 'date_created',
            'order' => 'DESC'
        ];
        $args = wp_parse_args($args, $defaults);

        $per_page = absint($args['per_page']);
        $current_page = absint($args['current_page']);
        $offset = ($current_page - 1) * $per_page;
        $status = sanitize_text_field($args['status']);
        $orderby = sanitize_key($args['orderby']); // Simple sanitization, adjust if complex columns
        $order = in_array(strtoupper($args['order']), ['ASC', 'DESC']) ? strtoupper($args['order']) : 'DESC';

        $where_clauses = [];
        $params = [];

        if (!empty($status)) {
            $where_clauses[] = "f.status = %s";
            $params[] = $status;
        }

        $where_sql = !empty($where_clauses) ? "WHERE " . implode(' AND ', $where_clauses) : '';

        // Get total count
        $count_sql = "SELECT COUNT(*) FROM {$this->table_name} f {$where_sql}";
        $total_items = $this->wpdb->get_var($this->wpdb->prepare($count_sql, $params));

        // Get feedback data
        $users_table = $this->wpdb->users;
        $sql = "SELECT f.*, u.display_name as assigned_name 
                FROM {$this->table_name} f 
                LEFT JOIN {$users_table} u ON f.assigned_id = u.ID 
                {$where_sql} 
                ORDER BY f.{$orderby} {$order} 
                LIMIT %d OFFSET %d";
        
        $params[] = $per_page;
        $params[] = $offset;
        
        $feedbacks = $this->wpdb->get_results($this->wpdb->prepare($sql, $params));

        return [
            'feedbacks' => $feedbacks,
            'total_items' => $total_items
        ];
    }

    public function count_unresolved() {
        return $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table_name} WHERE status != %s",
                'resolved'
            )
        );
    }

    public function get_status_info() {

        
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

        return $status_info;
    }
    
}
