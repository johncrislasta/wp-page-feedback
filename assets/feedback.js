jQuery(document).ready(function($) {
    let feedbackMode = false;
    let currentSelector = '';

    // Add modal HTML to the page
    const modalHtml = `
        <div class="wp-pf-modal">
            <div class="wp-pf-modal-content">
                <h2>Add Feedback</h2>
                <form id="wp-pf-feedback-form">
                    <div class="wp-pf-form-group">
                        <label for="wp-pf-action-type">Action Type</label>
                        <select id="wp-pf-action-type" required>
                            <option value="add">Add</option>
                            <option value="edit">Edit</option>
                            <option value="delete">Delete</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div class="wp-pf-form-group">
                        <label for="wp-pf-assigned-to">Assign To</label>
                        <select id="wp-pf-assigned-to">
                            <option value="">None</option>
                        </select>
                    </div>
                    <div class="wp-pf-form-group">
                        <label for="wp-pf-comment">Comment</label>
                        <textarea id="wp-pf-comment" required></textarea>
                    </div>
                    <div class="wp-pf-modal-actions">
                        <button type="button" class="wp-pf-cancel">Cancel</button>
                        <button type="submit" class="wp-pf-submit">Submit Feedback</button>
                    </div>
                </form>
            </div>
        </div>
    `;
    $('body').append(modalHtml);

    // Load users for assignment
    $.post(WP_PF_AJAX.ajax_url, {
        action: 'wp_page_feedback_get_users',
        _ajax_nonce: WP_PF_AJAX.nonce
    }, function(response) {
        if (response.success && response.data) {
            const select = $('#wp-pf-assigned-to');
            response.data.forEach(user => {
                select.append(`<option value="${user.ID}">${user.display_name}</option>`);
            });
        }
    });

    // Toggle feedback mode
    $('<button id="start-feedback">Start Feedback</button>')
        .css({ position: 'fixed', bottom: '20px', right: '20px', zIndex: 9999 })
        .appendTo('body')
        .click(function() {
            feedbackMode = !feedbackMode;
            $(this).text(feedbackMode ? 'Exit Feedback' : 'Start Feedback');
            feedbackMode ? $('body').addClass('wp-page-feedback-enabled') :  $('body').removeClass('wp-page-feedback-enabled');
            alert('Feedback mode ' + (feedbackMode ? 'enabled' : 'disabled'));
        });

    // Handle element click in feedback mode
    $(document).on('click', '*', function(e) {
        if (!feedbackMode) {
            return;
        }

        e.stopPropagation();

        if ($(this).parents('#wpadminbar').length) return;
        if ($(this).parents('.wp-pf-modal').length) return;

        e.preventDefault();

        if ($(this).is('#start-feedback')) return;
        if ($(this).is('#review-feedback')) return;

        currentSelector = getDomSelector(this);
        $('.wp-pf-modal').show();
    });

    // Handle modal close
    $('.wp-pf-cancel').click(function() {
        $('.wp-pf-modal').hide();
        $('#wp-pf-feedback-form')[0].reset();
    });

    function getDeviceInfo() {
        const width = window.innerWidth;
        const height = window.innerHeight;
        let deviceType = 'desktop';

        // Basic device detection based on screen width
        if (width <= 767) {
            deviceType = 'mobile';
        } else if (width <= 1024) {
            deviceType = 'tablet';
        }

        return {
            deviceType,
            screenWidth: width,
            screenHeight: height,
            userAgent: navigator.userAgent
        };
    }

    // Handle form submission
    $('#wp-pf-feedback-form').submit(function(e) {
        e.preventDefault();
        const deviceInfo = getDeviceInfo();

        const data = {
            action: 'wp_page_feedback_save',
            page_url: window.location.href,
            post_id: WP_PF_AJAX.post_id || null,
            comment: $('#wp-pf-comment').val(),
            selector: currentSelector,
            action_type: $('#wp-pf-action-type').val(),
            assigned_id: $('#wp-pf-assigned-to').val() || null,
            device_type: deviceInfo.deviceType,
            screen_width: deviceInfo.screenWidth,
            screen_height: deviceInfo.screenHeight,
            user_agent: deviceInfo.userAgent,
            _ajax_nonce: WP_PF_AJAX.nonce
        };

        $.post(WP_PF_AJAX.ajax_url, data, function(response) {
            if (response.success) {
                alert('Feedback submitted successfully!');
                $('.wp-pf-modal').hide();
                $('#wp-pf-feedback-form')[0].reset();
            } else {
                alert('Error submitting feedback. Please try again.');
            }
        });
    });

    function getDomSelector(el) {
        let path = '', node = el;
        
        while (node && node.nodeType === 1 && node.tagName.toLowerCase() !== 'body') {
            let selector = node.tagName.toLowerCase();
            let specificity = [];

            // Add ID if exists (highest specificity)
            if (node.id) {
                selector += '#' + node.id;
                path = selector + (path ? ' > ' + path : '');
                break;
            }

            // Always add nth-of-type for exact position
            let sib = node, nth = 1;
            while (sib.previousElementSibling) {
                sib = sib.previousElementSibling;
                if (sib.tagName === node.tagName) nth++;
            }
            specificity.push(`:nth-of-type(${nth})`);

            // Add classes if they exist
            if (node.classList && node.classList.length) {
                // Filter out dynamic or temporary classes
                const validClasses = Array.from(node.classList).filter(cls => 
                    !cls.startsWith('wp-') && // Skip WordPress dynamic classes
                    !cls.startsWith('js-') && // Skip JavaScript classes
                    !cls.startsWith('elementor-') && // Skip Elementor classes
                    !cls.match(/^[a-f0-9]{4,}$/) && // Skip likely dynamic hashes
                    !cls.includes('__') && // Skip BEM-style classes
                    !cls.includes('--')
                );

                if (validClasses.length) {
                    // Use all valid classes for maximum specificity
                    specificity.unshift(validClasses.map(c => '.' + c).join(''));
                }
            }

            // Add all relevant attributes for maximum specificity
            const relevantAttrs = ['role', 'data-type', 'type', 'name', 'aria-label', 'data-id', 'data-element-type'];
            relevantAttrs.forEach(attr => {
                if (node.hasAttribute(attr)) {
                    const value = node.getAttribute(attr);
                    if (value) {
                        specificity.push(`[${attr}="${value}"]`);
                    }
                }
            });

            // Add custom attributes that might help with specificity
            if (node.hasAttribute('href')) {
                const href = node.getAttribute('href');
                if (href && !href.includes('{{') && !href.includes('{%')) {
                    specificity.push(`[href="${href}"]`);
                }
            }

            // Add exact text content for leaf nodes if it's short and doesn't contain dynamic content
            if (!node.children.length && node.textContent) {
                const text = node.textContent.trim();
                if (text.length < 50 && !text.includes('{{') && !text.includes('{%')) {
                    specificity.push(`:contains("${text.replace(/"/g, '\\"')}")`);
                }
            }

            // Combine selector with specificity
            selector += specificity.join('');
            
            // Always use child combinators
            path = selector + (path ? ' > ' + path : '');
            
            node = node.parentNode;
        }

        // Add body as the root with nth-child for maximum specificity
        if (node && node.tagName.toLowerCase() === 'body') {
            let bodyIndex = 1;
            let sibling = node;
            while (sibling.previousElementSibling) {
                if (sibling.tagName.toLowerCase() === 'body') bodyIndex++;
                sibling = sibling.previousElementSibling;
            }
            path = `body:nth-child(${bodyIndex}) > ${path}`;
        } else {
            path = 'body > ' + path;
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
                action: 'wp_page_feedback_get',
                page_url: window.location.href,
                _ajax_nonce: WP_PF_AJAX.nonce
            }, function(response) {
                if (response.success && response.data.length) {
                    highlightedElements = response.data;
                    response.data.forEach(fb => {
                        // Unescape the selector before using it
                        const selector = fb.selector.replace(/\\+"/g, '"').replace(/\\+/g, '\\');
                        console.log(selector);
                        const $el = $(selector).first();
                        if ($el.length) {
                            $el.addClass('wp-pf-highlight  ' + fb.action_type)
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