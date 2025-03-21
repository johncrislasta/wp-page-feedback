jQuery(document).ready(function($) {
    let feedbackMode = false;

    $('<button id="start-feedback">Start Feedback</button>')
        .css({ position: 'fixed', bottom: '20px', right: '20px', zIndex: 9999 })
        .appendTo('body')
        .click(function() {
            feedbackMode = !feedbackMode;
            $(this).text(feedbackMode ? 'Exit Feedback' : 'Start Feedback');
            feedbackMode ? $('body').addClass('wp-page-feedback-enabled') :  $('body').removeClass('wp-page-feedback-enabled');
            alert('Feedback mode ' + (feedbackMode ? 'enabled' : 'disabled'));
        });

    $(document).on('click', '*', function(e) {
        if (!feedbackMode) {
            return;
        }

        e.preventDefault();
        e.stopPropagation();

        if ($(this).is('#start-feedback')) return;
        if ($(this).is('#review-feedback')) return;

        const selector = getDomSelector(this);
        const comment = prompt('Enter your feedback for this element:');
        if (!comment) return;

        $.post(WP_PF_AJAX.ajax_url, {
            action: 'wp_pf_save_feedback',
            comment,
            selector,
            page_url: window.location.href,
            _ajax_nonce: WP_PF_AJAX.nonce
        }, function(response) {
            if (response.success) alert('Feedback submitted!');
        });
    });

    function getDomSelector(el) {
        // Simple selector for demo (improve for production)
        let path = '', node = el;
        while (node && node.nodeType === 1 && node.tagName.toLowerCase() !== 'body') {
            let selector = node.tagName.toLowerCase();
            if (node.id) {
                selector += '#' + node.id;
                path = selector + ' ' + path;
                break;
            } else {
                let sib = node, nth = 1;
                while (sib.previousElementSibling) {
                    sib = sib.previousElementSibling;
                    nth++;
                }
                selector += `:nth-child(${nth})`;
            }
            path = selector + ' ' + path;
            node = node.parentNode;
        }
        return path.trim();
    }
});

jQuery(document).ready(function($) {
    let reviewActive = false;
    let highlightedElements = [];

    const $reviewBtn = $('<button id="review-feedback">Review Feedback</button>')
        .css({ position: 'fixed', bottom: '60px', right: '20px', zIndex: 9999 })
        .appendTo('body')
        .click(toggleReviewMode);

    function toggleReviewMode() {
        if (reviewActive) {
            // Turn off review mode
            $('.wp-pf-highlight').removeClass('wp-pf-highlight').removeAttr('title');
            reviewActive = false;
            $reviewBtn.text('Review Feedback (' + highlightedElements.length + ')');
        } else {
            // Fetch and highlight feedback
            $.post(WP_PF_AJAX.ajax_url, {
                action: 'wp_pf_get_feedback',
                page_url: window.location.href,
                _ajax_nonce: WP_PF_AJAX.nonce
            }, function(response) {
                if (response.success && response.data.length) {
                    highlightedElements = response.data;
                    response.data.forEach(fb => {
                        const $el = $(fb.selector).first();
                        if ($el.length) {
                            $el.addClass('wp-pf-highlight')
                                .attr('title', fb.comment);
                        }
                    });
                    reviewActive = true;
                    $reviewBtn.text('Hide Feedback (' + response.data.length + ')');
                } else {
                    alert('No feedback found for this page.');
                }
            });
        }
    }
});

