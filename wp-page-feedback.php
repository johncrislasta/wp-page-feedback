<?php
/**
 * Plugin Name: WP Page Feedback Annotator
 * Description: Allow clients to click on any page element and submit feedback, stored in WP Admin.
 * Version: 1.0.5
 * Author: JCYL.work
 */

if ( ! defined('ABSPATH') ) exit;

define('WP_PF_PATH', plugin_dir_path(__FILE__));

// Register Feedback Custom Post Type
require_once WP_PF_PATH . 'includes/class-feedback-cpt.php';

// Auto-Updater: GitHub Integration
require_once plugin_dir_path(__FILE__) . 'vendor/yahnis-elsts/plugin-update-checker/plugin-update-checker.php';

$updateChecker = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
	'https://github.com/johncrislasta/wp-page-feedback', // Replace with your repo
	__FILE__,
	'wp-page-feedback'
);

// Optional: If your main branch is "main" or "master"
$updateChecker->setBranch('master');

// Enqueue assets
add_action('wp_enqueue_scripts', function() {
	if (current_user_can('edit_pages')) { // Limit to editors/clients
		wp_enqueue_script('wp-pf-js', plugin_dir_url(__FILE__) . 'assets/feedback.js', ['jquery'], '1.0', true);
		wp_localize_script('wp-pf-js', 'WP_PF_AJAX', [
			'ajax_url' => admin_url('admin-ajax.php'),
			'nonce'    => wp_create_nonce('wp_pf_nonce')
		]);
		wp_enqueue_style(
			'wp-pf-feedback-css',
			plugin_dir_url(__FILE__) . 'assets/feedback.css',
			[],
			'1.0'
		);
	}
});

// AJAX handler to save feedback
add_action('wp_ajax_wp_pf_save_feedback', function() {
	check_ajax_referer('wp_pf_nonce');

	$comment = sanitize_textarea_field($_POST['comment']);
	$selector = sanitize_text_field($_POST['selector']);
	$page_url = esc_url_raw($_POST['page_url']);

	$feedback_id = wp_insert_post([
		'post_type'   => 'page_feedback',
		'post_title'  => 'Feedback on ' . $page_url,
		'post_status' => 'publish',
		'meta_input'  => compact('selector', 'comment', 'page_url', 'user_id')
	]);

	wp_send_json_success(['id' => $feedback_id]);
});

// AJAX: Fetch Feedback for Review Mode
add_action('wp_ajax_wp_pf_get_feedback', function() {
	check_ajax_referer('wp_pf_nonce');

    // Sanitize incoming URL
	$full_url = esc_url_raw($_POST['page_url']);

	// Strip query string
	$parsed = wp_parse_url($full_url);
	$page_url = isset($parsed['scheme']) && isset($parsed['host']) ?
		$parsed['scheme'] . '://' . $parsed['host'] . (isset($parsed['path']) ? $parsed['path'] : '') :
		$full_url;
;
	$feedbacks = get_posts([
		'post_type'  => 'page_feedback',
		'numberposts' => -1,
		'meta_query' => [
			['key' => 'page_url', 'value' => $page_url, 'compare' => '='],
			['key' => 'wp_pf_resolved', 'compare' => 'NOT EXISTS'],

		]
	]);

//    wp_die(json_encode([
//	    'post_type'  => 'page_feedback',
//	    'numberposts' => -1,
//	    'meta_query' => [
//		    ['key' => 'page_url', 'value' => $page_url, 'compare' => '='],
//		    ['key' => 'wp_pf_resolved', 'value' => "1", 'compare' => '!='],
//
//	    ]
//    ]));

	$results = [];
	foreach ($feedbacks as $fb) {
		$results[] = [
			'selector' => get_post_meta($fb->ID, 'selector', true),
			'comment'  => get_post_meta($fb->ID, 'comment', true)
		];
	}
	wp_send_json_success($results);
});

// Add "Resolved" Meta Box for page_feedback Post Type
add_action('add_meta_boxes', function() {
	add_meta_box('wp_pf_resolve_box', 'Resolve Feedback', 'wp_pf_resolve_meta_box', 'page_feedback', 'side');
});

function wp_pf_resolve_meta_box($post) {
	$resolved = get_post_meta($post->ID, 'wp_pf_resolved', true);
	?>
	<label>
		<input type="checkbox" name="wp_pf_resolved" value="1" <?php checked($resolved, '1'); ?>>
		Mark as Resolved
	</label>
	<?php
}

add_action('save_post_page_feedback', function($post_id) {
	if (isset($_POST['wp_pf_resolved'])) {
		update_post_meta($post_id, 'wp_pf_resolved', '1');
	} else {
		delete_post_meta($post_id, 'wp_pf_resolved');
	}
});


// Add "Resolved" Column to page_feedback Admin Table
add_filter('manage_page_feedback_posts_columns', function($columns) {
	$columns['page_url'] = 'Page';
	$columns['comment'] = 'Comment';
	$columns['resolved'] = 'Resolved';
	return $columns;
});

add_action('manage_page_feedback_posts_custom_column', function($column, $post_id) {

    if ($column === 'page_url') {
		$url = get_post_meta($post_id, 'page_url', true);
		if ($url) {
			echo '<a href="' . esc_url($url) . '?review_feedback=1" target="_blank">' . esc_html($url) . '</a>';
		}
	}

    if ($column === 'comment') {
		$comment = get_post_meta($post_id, 'comment', true);
		if ($comment) {
			echo $comment;
		}
	}

	if ($column === 'resolved') {
		$resolved = get_post_meta($post_id, 'wp_pf_resolved', true);
		if ($resolved) {
			echo '<span style="color:green;">✔ Resolved</span>';
		} else {
			echo '<button class="wp-pf-resolve-btn button" data-id="' . esc_attr($post_id) . '">Mark Resolved</button>';
		}
	}
}, 10, 2);

// AJAX Handler to Mark as Resolved
add_action('wp_ajax_wp_pf_mark_resolved', function() {
	if (!current_user_can('edit_posts')) wp_send_json_error('No permission');

	$post_id = intval($_POST['post_id']);
	if (get_post_type($post_id) !== 'page_feedback') wp_send_json_error('Invalid post');

	update_post_meta($post_id, 'wp_pf_resolved', '1');
	wp_send_json_success('Marked as resolved');
});

// Enqueue Admin JS in the Correct Context
add_action('admin_enqueue_scripts', function($hook) {
	if ($hook === 'edit.php' && isset($_GET['post_type']) && $_GET['post_type'] === 'page_feedback') {
		wp_enqueue_script('wp_pf_admin_js', plugin_dir_url(__FILE__) . 'assets/admin-feedback.js', ['jquery'], '1.0', true);
		wp_localize_script('wp_pf_admin_js', 'WP_PF_ADMIN', [
			'ajax_url' => admin_url('admin-ajax.php'),
			'nonce'    => wp_create_nonce('wp_pf_admin_nonce')
		]);
	}
});

// Make "Page" and "Resolved" Columns Sortable
add_filter('manage_edit-page_feedback_sortable_columns', function($columns) {
	$columns['page_url'] = 'page_url';
	return $columns;
});

// Handle the actual sorting by meta key
add_action('pre_get_posts', function($query) {
	if (!is_admin() || !$query->is_main_query()) return;

	if ($query->get('post_type') === 'page_feedback') {
		if ($query->get('orderby') === 'page_url') {
			$query->set('meta_key', 'page_url');
			$query->set('orderby', 'meta_value');
		}
	}
});
// Add custom filters above the table
add_action('restrict_manage_posts', function() {
	global $typenow;
	if ($typenow !== 'page_feedback') return;

	// Filter by Resolved Status
	$resolved_filter = $_GET['resolved_filter'] ?? '';
	?>
    <select name="resolved_filter">
        <option value="">All Status</option>
        <option value="1" <?php selected($resolved_filter, '1'); ?>>Resolved</option>
        <option value="0" <?php selected($resolved_filter, '0'); ?>>Unresolved</option>
    </select>
	<?php

	global $wpdb;

	// Query only distinct, non-empty page_url values from post meta
	$page_urls = $wpdb->get_col("
        SELECT DISTINCT meta_value 
        FROM {$wpdb->postmeta} 
        WHERE meta_key = 'page_url' 
        AND meta_value != ''
    ");

	if (!$page_urls) return;

	$current = isset($_GET['filter_page_url']) ? esc_url_raw($_GET['filter_page_url']) : '';

	echo '<select name="filter_page_url">';
	echo '<option value="">Filter by Page</option>';

	foreach ($page_urls as $url) {
		$selected = ($current === $url) ? 'selected' : '';
		echo '<option value="' . esc_attr($url) . '" ' . $selected . '>' . esc_html($url) . '</option>';
	}

	echo '</select>';
});

// Filter query based on selection
add_action('pre_get_posts', function($query) {
	if (!is_admin() || !$query->is_main_query() || $query->get('post_type') !== 'page_feedback') return;

	if (isset($_GET['resolved_filter'])) {

        if( $_GET['resolved_filter'] == 1 ) {

            $query->set('meta_query', [
                [
                    'key' => 'wp_pf_resolved',
                    'compare' => 'EXISTS'
                ]
            ]);
        } else if( $_GET['resolved_filter'] == 0 ) {

	        $query->set('meta_query', [
                [
                    'key' => 'wp_pf_resolved',
                    'compare' => 'NOT EXISTS'
                ]
            ]);
        }
	}

	if (!empty($_GET['page_filter'])) {
		$meta_query = $query->get('meta_query') ?: [];
		$meta_query[] = [
			'key' => 'page_url',
			'value' => esc_url_raw($_GET['page_filter']),
			'compare' => '='
		];
		$query->set('meta_query', $meta_query);
	}
});

// Add a Bubble Count for Unresolved Feedbacks in the Admin Menu
add_action('admin_menu', function() {
	global $menu, $wpdb;

	// Count unresolved feedbacks
	$unresolved_count = $wpdb->get_var("
        SELECT COUNT(*) FROM {$wpdb->posts} p
        LEFT JOIN {$wpdb->postmeta} m ON p.ID = m.post_id AND m.meta_key = 'wp_pf_resolved'
        WHERE p.post_type = 'page_feedback'
        AND p.post_status = 'publish'
        AND (m.meta_value IS NULL OR m.meta_value != '1')
    ");

	// Find the Page Feedback menu and append the count
	foreach ($menu as $key => $item) {
		if (isset($item[2]) && $item[2] === 'edit.php?post_type=page_feedback') {
			if ($unresolved_count > 0) {
				$menu[$key][0] .= ' <span class="update-plugins count-' . $unresolved_count . '"><span class="plugin-count">' . $unresolved_count . '</span></span>';
			}
			break;
		}
	}
}, 999);
