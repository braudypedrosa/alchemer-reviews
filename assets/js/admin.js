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
    });

})(jQuery); 