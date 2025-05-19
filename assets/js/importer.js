/**
 * Alchemer Reviews Importer JavaScript
 * 
 * Handles the Import Reviews functionality
 */
(function($) {
    'use strict';

    // Toast notification system
    const Toast = {
        container: null,
        
        init() {
            // Create toast container if it doesn't exist
            if (!this.container) {
                this.container = $('<div class="toast-container"></div>');
                $('body').append(this.container);
            }
        },
        
        show(title, message, type = 'success') {
            this.init();
            
            // Map type to appropriate icon and color
            const icons = {
                success: {
                    icon: '<svg class="w-6 h-6 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>',
                    color: 'success'
                },
                reject: {
                    icon: '<svg class="w-6 h-6 text-yellow-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>',
                    color: 'warning'
                },
                error: {
                    icon: '<svg class="w-6 h-6 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>',
                    color: 'error'
                }
            };
            
            const toastType = icons[type] || icons.success;
            
            const toast = $(`
                <div class="toast ${toastType.color}">
                    <div class="toast-icon">
                        ${toastType.icon}
                    </div>
                    <div class="toast-content">
                        <div class="toast-title">${title}</div>
                        <div class="toast-message">${message}</div>
                    </div>
                </div>
            `);
            
            this.container.append(toast);
            
            // Remove toast after 3 seconds
            setTimeout(() => {
                toast.addClass('removing');
                setTimeout(() => toast.remove(), 300);
            }, 3000);
        }
    };

    $(document).ready(function() {
        // Import Reviews button click handler
        $('#import-alchemer-reviews').on('click', function() {
            const $button = $(this);
            const $spinner = $('#import-spinner');
            const $result = $('#import-result');
            
            // Get import parameters
            const maxReviews = $('#max-reviews').val();
            const targetRating = $('#target-rating').val();
            
            // Disable button and show spinner
            $button.prop('disabled', true);
            $spinner.removeClass('hidden');
            $result.addClass('hidden');
            
            // Add progress bar
            $result.html(`
                <div class="progress-container">
                    <div class="progress-bar" style="width: 0%"></div>
                    <div class="progress-text">
                        <span class="status-text">Starting import...</span>
                    </div>
                </div>
            `).removeClass('hidden');
            
            // Simulate progress for better UX
            let progress = 0;
            const progressInterval = setInterval(() => {
                if (progress < 90) {
                    progress += Math.random() * 10;
                    $('.progress-bar').css('width', Math.min(progress, 90) + '%');
                }
            }, 500);
            
            // Make AJAX request
            $.ajax({
                url: alchemerReviewsImporter.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'import_alchemer_reviews',
                    nonce: alchemerReviewsImporter.nonce,
                    max_reviews: maxReviews,
                    target_rating: targetRating
                },
                xhr: function() {
                    const xhr = new window.XMLHttpRequest();
                    xhr.addEventListener('progress', function(evt) {
                        if (evt.lengthComputable) {
                            clearInterval(progressInterval);
                            const percentComplete = (evt.loaded / evt.total) * 100;
                            $('.progress-bar').css('width', percentComplete + '%');
                            $('.status-text').text('Importing reviews... ' + Math.round(percentComplete) + '%');
                        }
                    }, false);
                    return xhr;
                },
                success: function(response) {
                    clearInterval(progressInterval);
                    if (response.success) {
                        // Update progress to 100%
                        $('.progress-bar').css('width', '100%');
                        $('.status-text').text('Import complete! Loading reviews...');
                        
                        // Add debug logging
                        console.log('Import response:', response);
                        
                        // Validate reviews data
                        if (!response.data || !response.data.reviews || !Array.isArray(response.data.reviews)) {
                            console.error('Invalid reviews data:', response.data);
                            $result.html('<div class="alert alert-error">Error: Invalid review data received from server.</div>');
                            return;
                        }
                        
                        // Show reviews for approval after a short delay
                        setTimeout(function() {
                            try {
                            showReviewsForApproval(response.data.reviews);
                            } catch (error) {
                                console.error('Error showing reviews:', error);
                                $result.html('<div class="alert alert-error">Error: Failed to display reviews. Please check the console for details.</div>');
                            }
                        }, 500);
                    } else {
                        // Show error
                        $result.html('<div class="alert alert-error">' + response.data.message + '</div>');
                    }
                },
                error: function(xhr, status, error) {
                    clearInterval(progressInterval);
                    console.error('Import error:', error);
                    $result.html('<div class="alert alert-error">' + alchemerReviewsImporter.errorText + 'Failed to import reviews. Please check the console for details.</div>');
                },
                complete: function() {
                    clearInterval(progressInterval);
                    $button.prop('disabled', false);
                    $spinner.addClass('hidden');
                }
            });
        });
        
        // Show reviews for approval
        function showReviewsForApproval(reviews) {
            const $result = $('#import-result');
            let html = '<div class="reviews-to-approve">';
            
            // Add header with a dedicated class for easy updates
            html += '<h3 class="reviews-counter text-lg font-medium text-gray-700 mb-4">' + 
                    reviews.length + ' Reviews to Process</h3>';
            
            // Add each review
            reviews.forEach(function(review) {
                // Validate review data structure
                if (!review || !review.review_data) {
                    console.error('Invalid review data:', review);
                    return;
                }

                const reviewData = review.review_data;
                const aiAnalysis = reviewData.ai_analysis || { sentiment: 'Unknown', suggestion: reviewData.content };
                const responseId = reviewData.response_id;
                
                html += '<div class="review-card mb-6 p-4 bg-white rounded-lg shadow" data-response-id="' + responseId + '">';
                
                // Review header
                html += '<div class="flex justify-between items-center mb-4">';
                html += '<div class="flex items-center">';
                html += '<span class="text-lg font-medium">' + (reviewData.reviewer_name || 'Anonymous') + '</span>';
                html += '<span class="ml-2 text-sm text-gray-500">' + (reviewData.rating || '0') + ' ★</span>';
                html += '</div>';
                html += '<div class="sentiment-badge ' + (aiAnalysis.sentiment.toLowerCase() === 'positive' ? 'positive' : 'negative') + '">';
                html += aiAnalysis.sentiment;
                html += '</div>';
                html += '</div>';
                
                // Original content
                html += '<div class="mb-4">';
                html += '<h4 class="text-sm font-medium text-gray-700 mb-2">Original Review</h4>';
                html += '<div class="p-3 bg-gray-50 rounded">' + (reviewData.content || 'No content available') + '</div>';
                html += '</div>';
                
                // AI suggestion
                html += '<div class="mb-4">';
                html += '<h4 class="text-sm font-medium text-gray-700 mb-2">AI Suggestion</h4>';
                html += '<div class="p-3 bg-blue-50 rounded">' + (aiAnalysis.suggestion || reviewData.content || 'No suggestion available') + '</div>';
                html += '</div>';
                
                // Action buttons
                html += '<div class="review-actions">';
                html += '<button class="reject-review alchemer-button alchemer-button-secondary" data-response-id="' + responseId + '">Reject</button>';
                html += '<button class="accept-review alchemer-button alchemer-button-primary" data-response-id="' + responseId + '">Accept</button>';
                html += '<button class="accept-with-ai alchemer-button alchemer-button-primary" data-response-id="' + responseId + '">Accept with AI Suggestion</button>';
                html += '</div>';
                
                html += '</div>'; // End review-card
            });
            
            html += '</div>'; // End reviews-to-approve
            
            $result.html(html);
            
            // Add event handlers for accept/reject buttons
            $('.accept-review').on('click', function() {
                const responseId = $(this).data('response-id');
                const review = reviews.find(r => r.review_data.response_id == responseId);
                if (review) {
                    processReview(responseId, review, true, false);
                } else {
                    console.error('Review not found for response ID:', responseId);
                    Toast.show('Error', 'Review not found', 'error');
                }
            });
            
            $('.accept-with-ai').on('click', function() {
                const responseId = $(this).data('response-id');
                const review = reviews.find(r => r.review_data.response_id == responseId);
                if (review) {
                    processReview(responseId, review, true, true);
                } else {
                    console.error('Review not found for response ID:', responseId);
                    Toast.show('Error', 'Review not found', 'error');
                }
            });
            
            $('.reject-review').on('click', function() {
                const responseId = $(this).data('response-id');
                const review = reviews.find(r => r.review_data.response_id == responseId);
                if (review) {
                    processReview(responseId, review, false, false);
                } else {
                    console.error('Review not found for response ID:', responseId);
                    Toast.show('Error', 'Review not found', 'error');
                }
            });
        }
        
        // Process a single review
        function processReview(responseId, review, accept, useAI = false) {
            const $reviewCard = $('.review-card[data-response-id="' + responseId + '"]');
            const $button = accept ? 
                (useAI ? $reviewCard.find('.accept-with-ai') : $reviewCard.find('.accept-review')) :
                $reviewCard.find('.reject-review');
            
            $button.prop('disabled', true);
            $reviewCard.addClass('processing');
            
            // Prepare review data for processing
            const reviewData = {
                response_id: review.review_data.response_id,
                reviewer_name: review.review_data.reviewer_name || 'Anonymous',
                rating: review.review_data.rating || 0,
                content: accept && useAI ? 
                    (review.review_data.ai_analysis?.suggestion || review.review_data.content) : 
                    review.review_data.content,
                post_date: review.review_data.post_date || new Date().toISOString(),
                review_date: review.review_data.review_date || new Date().toLocaleDateString(),
                ai_analysis: {
                    sentiment: review.review_data.ai_analysis?.sentiment || 'Unknown',
                    suggestion: review.review_data.ai_analysis?.suggestion || review.review_data.content
                }
            };
            
            // Debug log
            console.log('Sending review data:', reviewData);
            
            $.ajax({
                url: alchemerReviewsImporter.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'process_alchemer_review',
                    nonce: alchemerReviewsImporter.nonce,
                    review_data: reviewData,
                    accept: accept ? 1 : 0,
                    use_ai: useAI ? 1 : 0
                },
                success: function(response) {
                    if (response.success) {
                        // Show success toast with appropriate message and type
                        Toast.show(
                            accept ? (useAI ? 'Review Accepted with AI Suggestion' : 'Review Accepted') : 'Review Rejected',
                            accept ? 
                                (useAI ? 
                                'The review has been published with the AI-suggested content.' :
                                    'The review has been published with the original content.') :
                                'The review has been saved as a draft with the original content. You can find it in the Reviews list.',
                            accept ? 'success' : 'reject'
                        );
                        
                        // Remove the review card with a fade animation
                        $reviewCard.fadeOut(300, function() {
                            $(this).remove();
                            
                            // Update the remaining reviews count using the dedicated class
                            const remainingReviews = $('.review-card').length;
                            if (remainingReviews > 0) {
                                $('.reviews-counter').text(remainingReviews + ' Reviews to Process');
                            } else {
                                $('#import-result').html(
                                    '<div class="alert alert-success">' +
                                    '<h4 class="text-lg font-medium mb-2">All reviews have been processed!</h4>' +
                                    '<p>You can find the published reviews in the Reviews list and drafts in the Drafts section.</p>' +
                                    '</div>'
                                );
                            }
                        });
                    } else {
                        // Show error toast
                        Toast.show(
                            'Error Processing Review',
                            response.data.message || 'An error occurred while processing the review.',
                            'error'
                        );
                        $button.prop('disabled', false);
                        $reviewCard.removeClass('processing');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Process error:', error);
                    console.error('Response:', xhr.responseText);
                    
                    // Show error toast
                    Toast.show(
                        'Error Processing Review',
                        'Failed to process review. Please check the console for details.',
                        'error'
                    );
                    
                    $button.prop('disabled', false);
                    $reviewCard.removeClass('processing');
                }
            });
        }
    });

})(jQuery); 