<?php
/**
 * Plugin Name: WP Page Feedback Annotator
 * Description: Allow clients to click on any page element and submit feedback, stored in WP Admin.
 * Version: 1.1.2
 * Author: JCYL.work
 */

if (!defined('ABSPATH')) exit;

define('WP_PF_PATH', plugin_dir_path(__FILE__));
define('WP_PF_VERSION', '1.1.2');
define('WP_PF_PLUGIN_FILE', __FILE__);

// Load required files
require_once WP_PF_PATH . 'includes/class-wp-page-feedback-model.php';
require_once WP_PF_PATH . 'includes/class-wp-page-feedback-installer.php';
require_once WP_PF_PATH . 'includes/class-wp-page-feedback-notifications.php';
require_once WP_PF_PATH . 'includes/class-wp-page-feedback.php';

// Auto-Updater: GitHub Integration
require_once plugin_dir_path(__FILE__) . 'vendor/yahnis-elsts/plugin-update-checker/plugin-update-checker.php';

// Initialize the plugin
add_action('plugins_loaded', function() {
    WP_Page_Feedback::get_instance();
    
    // Initialize notifications
    WP_Page_Feedback_Notifications::get_instance();
});

// Register activation hook
register_activation_hook(__FILE__, ['WP_Page_Feedback_Installer', 'install']);

// Register uninstallation hook
register_uninstall_hook(__FILE__, ['WP_Page_Feedback_Installer', 'uninstall']);

// Setup GitHub updater
$updateChecker = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
    'https://github.com/johncrislasta/wp-page-feedback',
    __FILE__,
    'wp-page-feedback'
);
$updateChecker->setBranch('master');
