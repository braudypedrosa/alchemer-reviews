/**
 * Alchemer Reviews Admin JavaScript
 * 
 * Handles the Test API Connection functionality and Skip Overwrite toggle
 */
(function($) {
    'use strict';

    $(document).ready(function() {
        // Tab switching logic for API test tabs
        $('.alchemer-tab-button').on('click', function() {
            const tab = $(this).data('tab');
            $('.alchemer-tab-button').removeClass('active border-blue-600 bg-white text-blue-700 shadow').addClass('border-transparent bg-gray-100 text-gray-600');
            $(this).addClass('active border-blue-600 bg-white text-blue-700 shadow').removeClass('border-transparent bg-gray-100 text-gray-600');
            $('.alchemer-tab-content').removeClass('alchemer-tab-active').hide();
            $('#' + tab).addClass('alchemer-tab-active').show();
            // ARIA attributes
            $('.alchemer-tab-button').attr('aria-selected', 'false');
            $(this).attr('aria-selected', 'true');
        });

        // Test API Connection button click handler (Alchemer)
        $('#test-alchemer-connection').on('click', function() {
            var $button = $(this);
            var $spinner = $('#connection-spinner');
            var $result = $('#test-connection-result');
            
            // Disable button and show spinner
            $button.prop('disabled', true);
            $spinner.removeClass('hidden');
            $result.html('<div class="p-3">' + alchemerReviewsAdmin.testingText + '</div>');
            
            // Send AJAX request
            $.ajax({
                url: alchemerReviewsAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'test_alchemer_api_connection',
                    nonce: alchemerReviewsAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $result.html('<div class="alert alert-success p-3 bg-green-50 text-green-800 rounded border-l-4 border-green-500">' + response.data.message + '</div>');
                    } else {
                        $result.html('<div class="alert alert-error p-3 bg-red-50 text-red-800 rounded border-l-4 border-red-500">' + alchemerReviewsAdmin.errorText + response.data.message + '</div>');
                    }
                },
                error: function(xhr, status, error) {
                    $result.html('<div class="alert alert-error p-3 bg-red-50 text-red-800 rounded border-l-4 border-red-500">' + alchemerReviewsAdmin.errorText + error + '</div>');
                },
                complete: function() {
                    // Re-enable button and hide spinner
                    $button.prop('disabled', false);
                    $spinner.addClass('hidden');
                }
            });
        });
        
        // Test API Connection button click handler (Gemini)
        $('#test-gemini-connection').on('click', function() {
            const $button = $(this);
            const $spinner = $('#gemini-connection-spinner');
            const $result = $('#test-gemini-connection-result');
            $button.prop('disabled', true);
            $spinner.removeClass('hidden');
            $result.html('<div class="p-3">' + alchemerReviewsAdmin.testingText + '</div>');
            $.ajax({
                url: alchemerReviewsAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'test_gemini_api_connection',
                    nonce: alchemerReviewsAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $result.html('<div class="alert alert-success p-3 bg-green-50 text-green-800 rounded border-l-4 border-green-500">' + response.data.message + '</div>');
                    } else {
                        $result.html('<div class="alert alert-error p-3 bg-red-50 text-red-800 rounded border-l-4 border-red-500">' + alchemerReviewsAdmin.errorText + response.data.message + '</div>');
                    }
                },
                error: function(xhr, status, error) {
                    $result.html('<div class="alert alert-error p-3 bg-red-50 text-red-800 rounded border-l-4 border-red-500">' + alchemerReviewsAdmin.errorText + error + '</div>');
                },
                complete: function() {
                    $button.prop('disabled', false);
                    $spinner.addClass('hidden');
                }
            });
        });
        
        // Skip Overwrite toggle click handler
        $(document).on('change', '.alchemer-toggle-skip-overwrite', function() {
            var $toggle = $(this);
            var postId = $toggle.data('post-id');
            var nonce = $toggle.data('nonce');
            var currentStatus = $toggle.data('status');
            var $statusText = $toggle.closest('.alchemer-toggle-container').find('.alchemer-toggle-status');
            
            // Temporarily disable the toggle
            $toggle.prop('disabled', true);
            
            // Show a quick notification using the WordPress admin notices system
            var $noticeContainer = $('<div class="notice notice-info is-dismissible"><p>' + alchemerReviewsAdmin.toggleSkipText + '</p></div>');
            
            // Send AJAX request
            $.ajax({
                url: alchemerReviewsAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'toggle_skip_overwrite',
                    post_id: postId,
                    nonce: nonce,
                    status: currentStatus
                },
                success: function(response) {
                    if (response.success) {
                        // Update the toggle data attribute
                        $toggle.data('status', response.data.new_status);
                        
                        // Update status text
                        if (response.data.new_status === 'on') {
                            $statusText.text(alchemerReviewsAdmin.protectedText);
                        } else {
                            $statusText.text(alchemerReviewsAdmin.unprotectedText);
                        }
                    } else {
                        // Revert the toggle if there was an error
                        $toggle.prop('checked', currentStatus === 'on');
                        
                        // Show error message
                        alert(alchemerReviewsAdmin.errorText + response.data.message);
                    }
                },
                error: function(xhr, status, error) {
                    // Revert the toggle if there was an error
                    $toggle.prop('checked', currentStatus === 'on');
                    
                    // Show error message
                    alert(alchemerReviewsAdmin.errorText + error);
                },
                complete: function() {
                    // Re-enable the toggle
                    $toggle.prop('disabled', false);
                }
            });
        });

        // Approve AI Review button click handler
        $(document).on('click', '.approve-ai-review', function(e) {
            e.preventDefault();
            const $btn = $(this);
            const postId = $btn.data('post-id');
            const aiSuggestion = $btn.data('ai-suggestion');
            // Create modal if it doesn't exist
            if ($('#ai-suggestion-modal').length === 0) {
                $('body').append(`
                    <div id="ai-suggestion-modal" class="alchemer-modal" style="display:none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 9999;">
                        <div class="alchemer-modal-content" style="background: white; margin: 10% auto; padding: 20px; width: 80%; max-width: 600px; border-radius: 5px;">
                            <h3>AI Suggestion</h3>
                            <div id="ai-suggestion-text" style="margin: 15px 0;"></div>
                            <div style="text-align: right;">
                                <button id="modal-cancel" class="button">Cancel</button>
                                <button id="modal-approve" class="button button-primary">Approve</button>
                            </div>
                        </div>
                    </div>
                `);
                $('#modal-cancel').on('click', function() {
                    $('#ai-suggestion-modal').hide();
                });
            }
            // Set AI suggestion text and show modal
            $('#ai-suggestion-text').text(aiSuggestion);
            $('#ai-suggestion-modal').show();
            // Handle modal approve button
            $('#modal-approve').off('click').on('click', function() {
                const $modalBtn = $(this);
                $modalBtn.prop('disabled', true).text('Approving...');
                $.ajax({
                    url: alchemerReviewsAdmin.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'approve_ai_review',
                        post_id: postId,
                        nonce: alchemerReviewsAdmin.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            $btn.closest('td').html('<span class="dashicons dashicons-yes" style="color:green;" title="Approved"></span>');
                            $('#ai-suggestion-modal').hide();
                        } else {
                            alert(alchemerReviewsAdmin.errorText + response.data.message);
                            $modalBtn.prop('disabled', false).text('Approve');
                        }
                    },
                    error: function(xhr, status, error) {
                        alert(alchemerReviewsAdmin.errorText + error);
                        $modalBtn.prop('disabled', false).text('Approve');
                    }
                });
            });
        });

        // Approve AI Suggestion button in meta box
        $('#approve-ai-suggestion').on('click', function() {
            const $btn = $(this);
            const postId = $btn.closest('.postbox').find('input[name="post_ID"]').val();
            const aiSuggestion = $('#ai_suggestion').val();
            const nonce = $('#alchemer_ai_suggestion_nonce').val();
            $btn.prop('disabled', true).text('Approving...');
            $.ajax({
                url: alchemerReviewsAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'approve_ai_review',
                    post_id: postId,
                    nonce: nonce,
                    ai_suggestion: aiSuggestion
                },
                success: function(response) {
                    if (response.success) {
                        $btn.closest('.postbox').find('.inside').html('<p>' + response.data.message + '</p>');
                    } else {
                        alert(alchemerReviewsAdmin.errorText + response.data.message);
                        $btn.prop('disabled', false).text('Approve AI Suggestion');
                    }
                },
                error: function(xhr, status, error) {
                    alert(alchemerReviewsAdmin.errorText + error);
                    $btn.prop('disabled', false).text('Approve AI Suggestion');
                }
            });
        });

        // Generate AI Suggestion button click handler
        $(document).on('click', '.generate-ai-suggestion', function(e) {
            e.preventDefault();
            const $btn = $(this);
            const postId = $btn.data('post-id');
            // Get the original review content from the row
            const $row = $btn.closest('tr');
            const originalContent = $row.find('td.column-review_content').text();

            // Create modal if it doesn't exist
            if ($('#ai-suggestion-modal').length === 0) {
                $('body').append(`
                    <div id="ai-suggestion-modal" class="alchemer-modal" style="display:none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 9999;">
                        <div class="alchemer-modal-content" style="background: white; margin: 5% auto; padding: 24px; width: 90%; max-width: 600px; border-radius: 8px;">
                            <h3 style="margin-top:0;">AI Suggestion</h3>
                            <div><strong>Original Review:</strong></div>
                            <div id="ai-original-text" style="margin: 10px 0 20px 0; padding: 10px; background: #f9fafb; border-radius: 4px; min-height: 40px;"></div>
                            <div><strong>AI Suggestion:</strong></div>
                            <div id="ai-suggestion-spinner" style="margin: 10px 0; text-align:center;"><span class="spinner is-active" style="float:none;display:inline-block;"></span> Generating suggestion...</div>
                            <div id="ai-suggestion-text" style="margin: 10px 0 20px 0; padding: 10px; background: #e0f2fe; border-radius: 4px; min-height: 40px; display:none;"></div>
                            <div style="text-align: right; margin-top: 20px;">
                                <button id="modal-reject" class="alchemer-button alchemer-button-secondary">Reject</button>
                                <button id="modal-accept-ai" class="alchemer-button alchemer-button-primary" disabled>Accept AI Suggestion</button>
                            </div>
                        </div>
                    </div>
                `);
                // Modal button handlers
                $(document).on('click', '#modal-reject', function() {
                    $('#ai-suggestion-modal').hide();
                });
            }
            // Set original content and reset modal
            $('#ai-original-text').text(originalContent);
            $('#ai-suggestion-text').hide().text('');
            $('#ai-suggestion-spinner').show();
            $('#modal-accept-ai').prop('disabled', true);
            $('#ai-suggestion-modal').show();

            // Fetch AI suggestion via AJAX
            $.ajax({
                url: alchemerReviewsAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'generate_ai_suggestion',
                    post_id: postId,
                    nonce: alchemerReviewsAdmin.nonce
                },
                success: function(response) {
                    if (response.success && response.data && response.data.suggestion) {
                        $('#ai-suggestion-text').text(response.data.suggestion).show();
                        $('#ai-suggestion-spinner').hide();
                        $('#modal-accept-ai').prop('disabled', false);
                    } else {
                        $('#ai-suggestion-spinner').html('<span style="color:red;">Failed to generate suggestion: ' + (response.data && response.data.message ? response.data.message : 'Unknown error') + '</span>');
                    }
                },
                error: function(xhr, status, error) {
                    $('#ai-suggestion-spinner').html('<span style="color:red;">Error: ' + error + '</span>');
                }
            });

            // Toast notification system (simple version if not present)
            function showToast(message, type = 'success') {
                // Remove any existing toast
                $('.alchemer-toast').remove();
                const color = type === 'success' ? '#10b981' : (type === 'error' ? '#ef4444' : '#f59e42');
                const toast = $(`
                    <div class="alchemer-toast" style="position:fixed;top:30px;right:30px;z-index:99999;background:#fff;border-left:6px solid ${color};box-shadow:0 2px 8px rgba(0,0,0,0.08);padding:16px 32px 16px 20px;border-radius:8px;font-size:16px;color:#222;min-width:220px;">
                        <span>${message}</span>
                    </div>
                `);
                $('body').append(toast);
                setTimeout(() => { toast.fadeOut(400, function(){ $(this).remove(); }); }, 2500);
            }

            // Accept AI Suggestion handler
            $('#modal-accept-ai').off('click').on('click', function() {
                const $acceptBtn = $(this);
                $acceptBtn.prop('disabled', true).text('Publishing...');
                const aiSuggestion = $('#ai-suggestion-text').text();
                $.ajax({
                    url: alchemerReviewsAdmin.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'approve_ai_review',
                        post_id: postId,
                        nonce: alchemerReviewsAdmin.nonce,
                        ai_suggestion: aiSuggestion
                    },
                    success: function(response) {
                        if (response.success) {
                            $('#ai-suggestion-modal').hide();
                            $row.find('td.column-review_content').text(aiSuggestion);
                            showToast('Review published with AI suggestion!', 'success');
                        } else {
                            alert(alchemerReviewsAdmin.errorText + (response.data && response.data.message ? response.data.message : 'Unknown error'));
                            $acceptBtn.prop('disabled', false).text('Accept AI Suggestion');
                        }
                    },
                    error: function(xhr, status, error) {
                        alert(alchemerReviewsAdmin.errorText + error);
                        $acceptBtn.prop('disabled', false).text('Accept AI Suggestion');
                    }
                });
            });
        });
    });

})(jQuery); 