<?php
add_action('init', function() {

	register_post_type('page_feedback', [
		'labels' => [
			'name'                  => 'Page Feedback',
			'singular_name'         => 'Feedback',
			'menu_name'             => 'Feedback',
			'name_admin_bar'        => 'Feedback',
			'add_new'               => 'Add New Feedback',
			'add_new_item'          => 'Add New Feedback',
			'new_item'              => 'New Feedback',
			'edit_item'             => 'Edit Feedback',
			'view_item'             => 'View Feedback',
			'all_items'             => 'All Feedback',
			'search_items'          => 'Search Feedback',
			'not_found'             => 'No feedback found',
			'not_found_in_trash'    => 'No feedback found in Trash',
		],
		'public'             => false,
		'show_ui'            => true,
		'show_in_menu'       => true,
		'menu_position'      => 25,
		'menu_icon'          => 'dashicons-feedback', // Optional: Use a relevant Dashicon
		'supports'           => ['title', 'custom-fields', 'author'],  // Add more supports if needed
		'capability_type'    => 'post',
		'map_meta_cap'       => true,
	]);


});
