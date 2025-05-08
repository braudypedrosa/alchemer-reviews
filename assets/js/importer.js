/**
 * Alchemer Reviews Importer JavaScript
 * 
 * Handles the Import Reviews functionality
 */
(function($) {
    'use strict';

    $(document).ready(function() {
        // Import Reviews button click handler
        $('#import-alchemer-reviews').on('click', function() {
            var $button = $(this);
            var $spinner = $('#import-spinner');
            var $result = $('#import-result');
            
            // Get filter values
            var maxReviews = $('#max-reviews').val();
            var targetRating = $('#target-rating').val();
            
            // Disable button and show spinner
            $button.prop('disabled', true);
            $spinner.removeClass('hidden');
            
            // Show processing message
            $result.removeClass('hidden').html(
                '<div class="alert alert-info p-4 bg-blue-50 text-blue-800 rounded border-l-4 border-blue-500">' +
                '<div class="flex items-center">' +
                '<span class="dashicons dashicons-update-alt mr-2 animate-spin"></span>' +
                '<div>' + alchemerReviewsImporter.importingText + 
                (targetRating > 0 ? ' (' + targetRating + ' ' + alchemerReviewsImporter.starsOnlyText + ')' : '') + 
                '</div>' +
                '</div>' +
                '</div>'
            );
            
            // Send AJAX request
            $.ajax({
                url: alchemerReviewsImporter.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'import_alchemer_reviews',
                    nonce: alchemerReviewsImporter.nonce,
                    max_reviews: maxReviews,
                    target_rating: targetRating
                },
                success: function(response) {
                    if (response.success) {
                        // Create success message with stats
                        var stats = response.data.counts;
                        var filters = response.data.filters;
                        var statsHtml = '';
                        
                        if (stats) {
                            statsHtml = 
                                '<div class="grid grid-cols-3 gap-4 mt-6">' +
                                '<div class="stats-card stats-card-created">' +
                                    '<div class="stats-card-value">' + stats.created + '</div>' +
                                    '<div class="stats-card-label">' + alchemerReviewsImporter.createdText + '</div>' +
                                '</div>' +
                                '<div class="stats-card stats-card-updated">' +
                                    '<div class="stats-card-value">' + stats.updated + '</div>' +
                                    '<div class="stats-card-label">' + alchemerReviewsImporter.updatedText + '</div>' +
                                '</div>' +
                                '<div class="stats-card stats-card-skipped">' +
                                    '<div class="stats-card-value">' + (parseInt(stats.skipped_edited) + parseInt(stats.skipped)) + '</div>' +
                                    '<div class="stats-card-label">' + alchemerReviewsImporter.skippedText + '</div>' +
                                '</div>' +
                                '</div>';
                                
                            // Add filter information if applied
                            if (filters && filters.target_rating > 0) {
                                statsHtml += '<div class="mt-4 p-3 bg-blue-50 rounded text-blue-800 text-sm">' +
                                    '<span class="dashicons dashicons-filter mr-1"></span> ' +
                                    alchemerReviewsImporter.filteredText + ': ' + 
                                    filters.target_rating + ' ' + alchemerReviewsImporter.starsOnlyText +
                                    '</div>';
                            }
                        }
                        
                        $result.html(
                            '<div class="alert alert-success p-4 bg-green-50 text-green-800 rounded border-l-4 border-green-500 mb-4">' +
                                '<div class="flex items-center mb-2">' +
                                    '<span class="dashicons dashicons-yes-alt mr-2"></span>' +
                                    '<h3 class="text-lg font-medium">Import Complete</h3>' +
                                '</div>' +
                                '<p>' + response.data.message + '</p>' +
                            '</div>' + 
                            statsHtml
                        );
                    } else {
                        $result.html(
                            '<div class="alert alert-error p-4 bg-red-50 text-red-800 rounded border-l-4 border-red-500">' +
                                '<div class="flex items-center mb-2">' +
                                    '<span class="dashicons dashicons-warning mr-2"></span>' +
                                    '<h3 class="text-lg font-medium">Import Failed</h3>' +
                                '</div>' +
                                '<p>' + response.data.message + '</p>' +
                            '</div>'
                        );
                    }
                },
                error: function(xhr, status, error) {
                    $result.html(
                        '<div class="alert alert-error p-4 bg-red-50 text-red-800 rounded border-l-4 border-red-500">' +
                            '<div class="flex items-center mb-2">' +
                                '<span class="dashicons dashicons-warning mr-2"></span>' +
                                '<h3 class="text-lg font-medium">Error</h3>' +
                            '</div>' +
                            '<p>' + alchemerReviewsImporter.errorText + error + '</p>' +
                        '</div>'
                    );
                },
                complete: function() {
                    // Re-enable button and hide spinner
                    $button.prop('disabled', false);
                    $spinner.addClass('hidden');
                }
            });
        });
    });

})(jQuery); 