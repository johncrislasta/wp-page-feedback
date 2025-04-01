<?php

class WP_Page_Feedback_Installer {
    private static $table_name;
    private static $charset_collate;
    private static $wpdb;

    public static function init() {
        global $wpdb;
        self::$wpdb = $wpdb;
        self::$table_name = $wpdb->prefix . 'page_feedbacks';
        self::$charset_collate = $wpdb->get_charset_collate();
    }

    public static function install() {
        self::init();
        self::create_table();
    }

    private static function create_table() {
        $sql = "CREATE TABLE IF NOT EXISTS " . self::$table_name . " (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            comment text NOT NULL,
            selector text NOT NULL,
            page_url varchar(255) NOT NULL,
            post_id bigint(20),
            author_id bigint(20) NOT NULL,
            assigned_id bigint(20),
            status varchar(20) DEFAULT 'pending' CHECK (status IN ('pending', 'in_progress', 'reviewing', 'approved', 'rejected', 'resolved', 'deferred')),
            action_type ENUM('add', 'delete', 'edit', 'other') DEFAULT 'other',
            device_type ENUM('mobile', 'tablet', 'desktop') DEFAULT 'desktop',
            screen_width int(11),
            screen_height int(11),
            screenshot_url text,
            user_agent text,
            date_created datetime DEFAULT CURRENT_TIMESTAMP,
            date_modified datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY page_url (page_url),
            KEY status (status),
            KEY author_id (author_id),
            KEY assigned_id (assigned_id),
            KEY device_type (device_type)
        ) " . self::$charset_collate . ";";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    public static function uninstall() {
        self::init();
        $sql = "DROP TABLE IF EXISTS " . self::$table_name;
        self::$wpdb->query($sql);
    }
}
