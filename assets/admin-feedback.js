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

// Handle status changes
document.querySelectorAll('.wp-pf-status-select').forEach(select => {
    // Set initial status color
    const currentStatus = select.value;
    select.setAttribute('data-status', currentStatus);
    select.previousElementSibling?.setAttribute('data-status', currentStatus);

    select.addEventListener('change', async function() {
        const feedbackId = this.dataset.id;
        const newStatus = this.value;
        
        try {
            console.log('Sending request with:', {
                action: 'wp_page_feedback_update_status',
                feedback_id: feedbackId,
                status: newStatus,
                _ajax_nonce: wpPageFeedback.nonce
            });
            
            const response = await fetch(wpPageFeedback.ajaxurl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'wp_page_feedback_update_status',
                    feedback_id: feedbackId,
                    status: newStatus,
                    _ajax_nonce: wpPageFeedback.nonce
                })
            });

            const data = await response.json();
            console.log('Response:', data);
            
            if (data.success) {
                // Update the status color
                this.setAttribute('data-status', newStatus);
                const icon = this.parentElement.querySelector('.wp-pf-status-icon');
                if (icon) {
                    icon.setAttribute('data-status', newStatus);
                    // Update icon
                    const statusInfo = {
                        'pending': 'clock',
                        'in_progress': 'controls-play',
                        'reviewing': 'search',
                        'approved': 'yes-alt',
                        'rejected': 'no-alt',
                        'resolved': 'yes-alt',
                        'deferred': 'backup'
                    };
                    icon.className = `wp-pf-status-icon dashicons dashicons-${statusInfo[newStatus]}`;
                }
            } else {
                // Revert to previous status on error
                this.value = currentStatus;
                alert('Failed to update status. Please try again.');
            }
        } catch (error) {
            console.error('Error updating status:', error);
            this.value = currentStatus;
            alert('Failed to update status. Please try again.');
        }
    });
});
