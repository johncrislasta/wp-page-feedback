jQuery(document).ready(function($) {
    $('.wp-pf-resolve-btn').click(function() {
        const postId = $(this).data('id');
        const $btn = $(this);
        $.post(WP_PF_ADMIN.ajax_url, {
            action: 'wp_pf_mark_resolved',
            post_id: postId,
            _ajax_nonce: WP_PF_ADMIN.nonce
        }, function(response) {
            if (response.success) {
                $btn.replaceWith('<span style="color:green;">✔ Resolved</span>');
            } else {
                alert('Failed to resolve.');
            }
        });
    });
});
