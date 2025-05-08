<?php
/**
 * Class for handling Alchemer API communications
 *
 * @since 1.0.0
 */
class Alchemer_Reviews_API {

    /**
     * API base URL
     *
     * @var string
     */
    private $api_base_url = 'https://api.alchemer.com/v5/';

    /**
     * API token
     *
     * @var string
     */
    private $api_token;

    /**
     * API token secret
     *
     * @var string
     */
    private $api_token_secret;

    /**
     * Survey ID
     *
     * @var string
     */
    private $survey_id;

    /**
     * Constructor
     * 
     * @param string $api_token API token
     * @param string $api_token_secret API token secret
     * @param string $survey_id Survey ID
     */
    public function __construct( $api_token = '', $api_token_secret = '', $survey_id = '' ) {
        $this->api_token = $api_token;
        $this->api_token_secret = $api_token_secret;
        $this->survey_id = $survey_id;
        
        // If no credentials provided, try to get them from settings
        if ( empty( $this->api_token ) || empty( $this->api_token_secret ) || empty( $this->survey_id ) ) {
            $this->load_credentials_from_settings();
        }
    }
    
    /**
     * Load API credentials from plugin settings
     * 
     * @return void
     */
    private function load_credentials_from_settings() {
        $settings = get_option( 'alchemer_reviews_settings', array() );
        
        if ( isset( $settings['api_token'] ) ) {
            $this->api_token = $settings['api_token'];
        }
        
        if ( isset( $settings['api_token_secret'] ) ) {
            $this->api_token_secret = $settings['api_token_secret'];
        }
        
        if ( isset( $settings['survey_id'] ) ) {
            $this->survey_id = $settings['survey_id'];
        }
    }
    
    /**
     * Build API URL with auth parameters based on the sample URL format
     * 
     * @param string $endpoint API endpoint
     * @param array $params Additional query parameters
     * @return string Full API URL with auth parameters
     */
    private function build_api_url( $endpoint, $params = array() ) {
        $url = $this->api_base_url . $endpoint;
        
        // Add authentication parameters according to the sample URL format
        $url .= '?api_token=' . $this->api_token;
        
        // For the API token secret, we keep the URL-encoded characters as is
        // Don't apply additional encoding to prevent double-encoding
        $url .= '&api_token_secret=' . $this->api_token_secret;
        
        // Add any additional parameters
        if (!empty($params)) {
            foreach ($params as $key => $value) {
                $url .= '&' . $key . '=' . urlencode($value);
            }
        }
        
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'Built API URL: ' . $url );
        }
        
        return $url;
    }
    
    /**
     * Test API connection
     * 
     * @return array Response with success status and message
     */
    public function test_connection() {
        // Check if we have API credentials
        if ( empty( $this->api_token ) || empty( $this->api_token_secret ) ) {
            return array(
                'success' => false,
                'message' => __( 'API credentials are not configured. Please enter your API Token and API Token Secret.', 'alchemer-reviews' ),
            );
        }
        
        // Log credentials for debugging (only in test environments)
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'Alchemer API test connection - API Token: ' . substr($this->api_token, 0, 10) . '...' );
            error_log( 'Alchemer API test connection - API Secret: ' . substr( $this->api_token_secret, 0, 5 ) . '...' );
        }
        
        // First, try to get the account details to verify API connectivity
        $url = $this->build_api_url( 'account' );
        
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'Testing API URL: ' . $url );
        }
        
        // Make the request
        $response = wp_remote_get( $url, array(
            'timeout' => 15,
            'headers' => array(
                'Accept' => 'application/json',
            ),
            'sslverify' => true,
        ) );
        
        // Check for wp_remote_get errors
        if ( is_wp_error( $response ) ) {
            return array(
                'success' => false,
                'message' => $response->get_error_message(),
            );
        }
        
        // Get response code
        $response_code = wp_remote_retrieve_response_code( $response );
        
        // Parse response body
        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );
        
        // Log response for debugging (only in test environments)
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'Alchemer API response code: ' . $response_code );
            error_log( 'Alchemer API response body: ' . $body );
        }
        
        // Check response code
        if ( $response_code !== 200 ) {
            $error_message = isset( $data['message'] ) ? $data['message'] : __( 'Unknown error', 'alchemer-reviews' );
            
            // Provide more context for 401 errors
            if ( $response_code === 401 ) {
                $error_message = __( 'Authentication failed. Please verify your API Token and API Token Secret are correct and have not been regenerated.', 'alchemer-reviews' );
            }
            
            return array(
                'success' => false,
                'message' => sprintf( __( 'API error (code %d): %s', 'alchemer-reviews' ), $response_code, $error_message ),
            );
        }
        
        // API connection successful, now verify if the survey ID is valid
        if (empty($this->survey_id)) {
            return array(
                'success' => true,
                'message' => __( 'API connection successful! Please enter a Survey ID to complete the setup.', 'alchemer-reviews' ),
                'account_info' => isset($data['data']) ? $data['data'] : array(),
            );
        }
        
        // Test the survey access
        return $this->verify_survey_access();
    }
    
    /**
     * Verify access to the configured survey
     * 
     * @return array Response with success status and message
     */
    private function verify_survey_access() {
        // Try to get the survey details
        $url = $this->build_api_url( 'survey/' . $this->survey_id );
        
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'Testing survey access URL: ' . $url );
        }
        
        // Make the request
        $response = wp_remote_get( $url, array(
            'timeout' => 15,
            'headers' => array(
                'Accept' => 'application/json',
            ),
            'sslverify' => true,
        ) );
        
        // Check for wp_remote_get errors
        if ( is_wp_error( $response ) ) {
            return array(
                'success' => false,
                'message' => sprintf( __( 'API connection successful, but survey access failed: %s', 'alchemer-reviews' ), $response->get_error_message() ),
            );
        }
        
        // Get response code
        $response_code = wp_remote_retrieve_response_code( $response );
        
        // Parse response body
        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );
        
        // Log survey response for debugging
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'Survey API response code: ' . $response_code );
        }
        
        // Check response code
        if ( $response_code !== 200 ) {
            // Try the survey responses endpoint as a fallback
            return $this->test_survey_responses();
        }
        
        // Check if we got the expected data
        if ( ! isset( $data['data'] ) || empty( $data['data'] ) ) {
            return array(
                'success' => false,
                'message' => __( 'API connection successful, but the survey was not found or returned no data.', 'alchemer-reviews' ),
            );
        }
        
        // Success! Return survey title
        $survey_title = isset( $data['data']['title'] ) ? $data['data']['title'] : __( 'Unknown survey title', 'alchemer-reviews' );
        
        return array(
            'success' => true,
            'message' => sprintf( __( 'Connection successful! Account verified and Survey found: %s', 'alchemer-reviews' ), $survey_title ),
            'survey_info' => $data['data'],
        );
    }
    
    /**
     * Test connection to survey responses as a fallback
     * 
     * @return array Response with success status and message
     */
    private function test_survey_responses() {
        // Try to get survey responses as a fallback
        $url = $this->build_api_url( 'survey/' . $this->survey_id . '/surveyresponse', array(
            'resultsperpage' => 1,
            'order_by' => '-date_submitted'
        ));
        
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'Testing fallback API URL: ' . $url );
        }
        
        // Make the request
        $response = wp_remote_get( $url, array(
            'timeout' => 15,
            'headers' => array(
                'Accept' => 'application/json',
            ),
            'sslverify' => true,
        ) );
        
        // Check for wp_remote_get errors
        if ( is_wp_error( $response ) ) {
            return array(
                'success' => false,
                'message' => $response->get_error_message(),
            );
        }
        
        // Get response code
        $response_code = wp_remote_retrieve_response_code( $response );
        
        // Parse response body
        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );
        
        // Log response for debugging (only in test environments)
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'Alchemer API fallback response code: ' . $response_code );
            error_log( 'Alchemer API fallback response body: ' . $body );
        }
        
        // Check response code
        if ( $response_code !== 200 ) {
            $error_message = isset( $data['message'] ) ? $data['message'] : __( 'Unknown error', 'alchemer-reviews' );
            
            return array(
                'success' => false,
                'message' => sprintf( __( 'API connection successful, but survey ID %s is not accessible. Error: %s', 'alchemer-reviews' ), $this->survey_id, $error_message ),
            );
        }
        
        // Check if we got responses
        $total_count = isset( $data['total_count'] ) ? intval( $data['total_count'] ) : 0;
        
        // Success! We have access to the survey responses
        return array(
            'success' => true,
            'message' => sprintf( __( 'Connection successful! Account verified and Survey responses accessible. Total responses: %d', 'alchemer-reviews' ), $total_count ),
            'response_info' => $data,
        );
    }
    
    /**
     * Get survey details including questions
     * 
     * @return array Response with success status, message and data
     */
    public function get_survey_details() {
        // Check if we have API credentials
        if ( empty( $this->api_token ) || empty( $this->api_token_secret ) || empty( $this->survey_id ) ) {
            return array(
                'success' => false,
                'message' => __( 'API credentials are not configured. Please enter your API Token, API Token Secret, and Survey ID.', 'alchemer-reviews' ),
                'data' => array(),
            );
        }
        
        // Build URL
        $url = $this->build_api_url( 'survey/' . $this->survey_id );
        
        // Make the request
        $response = wp_remote_get( $url, array(
            'timeout' => 15,
            'headers' => array(
                'Accept' => 'application/json',
            ),
            'sslverify' => true,
        ) );
        
        // Check for wp_remote_get errors
        if ( is_wp_error( $response ) ) {
            return array(
                'success' => false,
                'message' => $response->get_error_message(),
                'data' => array(),
            );
        }
        
        // Get response code
        $response_code = wp_remote_retrieve_response_code( $response );
        
        // Parse response body
        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );
        
        // Check response code
        if ( $response_code !== 200 ) {
            $error_message = isset( $data['message'] ) ? $data['message'] : __( 'Unknown error', 'alchemer-reviews' );
            
            return array(
                'success' => false,
                'message' => sprintf( __( 'API error (code %d): %s', 'alchemer-reviews' ), $response_code, $error_message ),
                'data' => array(),
            );
        }
        
        // Check if the survey data exists
        if ( ! isset( $data['data'] ) ) {
            return array(
                'success' => false,
                'message' => __( 'Invalid survey data received from API', 'alchemer-reviews' ),
                'data' => array(),
            );
        }
        
        return array(
            'success' => true,
            'message' => __( 'Survey details retrieved successfully', 'alchemer-reviews' ),
            'data' => $data['data'],
        );
    }

    /**
     * Get survey responses
     * 
     * @param array $args Additional arguments for the request
     * @return array Response with success status, message and data
     */
    public function get_survey_responses( $args = array() ) {
        // Check if we have API credentials
        if ( empty( $this->api_token ) || empty( $this->api_token_secret ) || empty( $this->survey_id ) ) {
            return array(
                'success' => false,
                'message' => __( 'API credentials are not configured. Please enter your API Token, API Token Secret, and Survey ID.', 'alchemer-reviews' ),
                'data' => array(),
            );
        }
        
        // Default arguments
        $default_args = array(
            'page' => 1,
            'resultsperpage' => 50,
            'completed' => 'true',
            'filter_field' => '',
            'filter_value' => '',
            'filter_condition' => '',
            'fields' => '',
            'order_by' => '-date_submitted', // Order by newest responses first
        );
        
        // Merge with provided arguments
        $args = wp_parse_args( $args, $default_args );
        
        // Extract arguments
        $page = $args['page'];
        $resultsperpage = $args['resultsperpage'];
        $completed = $args['completed'];
        $filter_field = $args['filter_field'];
        $filter_value = $args['filter_value'];
        $filter_condition = $args['filter_condition'];
        $fields = $args['fields'];
        $order_by = $args['order_by'];
        
        // Build query parameters
        $params = array(
            'page' => $page,
            'resultsperpage' => $resultsperpage,
            'completed' => $completed,
            'order_by' => $order_by // Add order_by parameter to sort by newest first
        );
        
        // Add filter if provided
        if ( ! empty( $filter_field ) && ! empty( $filter_value ) ) {
            $params['filter[field][0]'] = $filter_field;
            $params['filter[value][0]'] = $filter_value;
            
            if ( ! empty( $filter_condition ) ) {
                $params['filter[condition][0]'] = $filter_condition;
            }
        }
        
        // Add fields if provided
        if ( ! empty( $fields ) ) {
            $params['fields'] = $fields;
        }
        
        // Build URL
        $url = $this->build_api_url( 'survey/' . $this->survey_id . '/surveyresponse', $params );
        
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'Fetching survey responses with URL: ' . $url );
        }
        
        // Make the request
        $response = wp_remote_get( $url, array(
            'timeout' => 30, // Increase timeout for potentially large datasets
            'headers' => array(
                'Accept' => 'application/json',
            ),
            'sslverify' => true,
        ) );
        
        // Check for wp_remote_get errors
        if ( is_wp_error( $response ) ) {
            return array(
                'success' => false,
                'message' => $response->get_error_message(),
                'data' => array(),
            );
        }
        
        // Get response code
        $response_code = wp_remote_retrieve_response_code( $response );
        
        // Parse response body
        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );
        
        // Check response code
        if ( $response_code !== 200 ) {
            $error_message = isset( $data['message'] ) ? $data['message'] : __( 'Unknown error', 'alchemer-reviews' );
            
            return array(
                'success' => false,
                'message' => sprintf( __( 'API error (code %d): %s', 'alchemer-reviews' ), $response_code, $error_message ),
                'data' => array(),
            );
        }
        
        // Check if responses exist
        if ( ! isset( $data['data'] ) ) {
            return array(
                'success' => true, // Still successful, just no responses
                'message' => __( 'No survey responses found', 'alchemer-reviews' ),
                'data' => array(),
            );
        }
        
        return array(
            'success' => true,
            'message' => sprintf( __( 'Successfully retrieved %d survey responses', 'alchemer-reviews' ), count( $data['data'] ) ),
            'data' => $data['data'],
            'total' => isset( $data['total_count'] ) ? $data['total_count'] : count( $data['data'] ),
            'page' => $page,
            'per_page' => $resultsperpage,
            'total_pages' => isset( $data['total_pages'] ) ? $data['total_pages'] : 1,
        );
    }
    
    /**
     * Get filtered survey responses with recursive pagination to match criteria
     * 
     * @param array $args Filter arguments
     * @param int $max_reviews Maximum number of reviews to fetch
     * @param int $target_rating Specific rating to filter by (1-5)
     * @return array Response with success status, message and data
     */
    public function get_filtered_responses($args = array(), $max_reviews = 20, $target_rating = 0) {
        // Default arguments - DO NOT set max_reviews as resultsperpage
        $default_args = array(
            'page' => 1,
            'resultsperpage' => 50, // Fixed value - standard pagination size
            'completed' => 'true',
            'order_by' => '-date_submitted', // Newest first
        );
        
        // Merge with provided arguments
        $args = wp_parse_args($args, $default_args);
        
        // Maximum reviews defaults to 20 if invalid
        $max_reviews = max(1, intval($max_reviews));
        // Cap at reasonable maximum
        $max_reviews = min(100, $max_reviews);
        
        // Get rating question field mapping to use for filtering
        $field_mappings = get_option('alchemer_reviews_field_mappings', array());
        $rating_question_id = !empty($field_mappings['rating_question']) ? $field_mappings['rating_question'] : '';
        
        // Debug summary at the start
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("===== FILTERED RESPONSE SEARCH STARTED =====");
            error_log("Target: EXACTLY {$max_reviews} valid reviews with rating {$target_rating}");
            error_log("Rating question ID: {$rating_question_id}");
            error_log("Results per page: {$args['resultsperpage']}");
            error_log("Will check for both correct rating AND non-empty content");
        }
        
        // If we have a specific rating to filter by and have the rating question ID
        if ($target_rating > 0 && !empty($rating_question_id)) {
            // Add filter for the rating question using Alchemer's exact syntax:
            // https://apihelp.alchemer.com/help/filters-v5
            $args['filter[field][0]'] = "[question({$rating_question_id})]";
            $args['filter[operator][0]'] = "="; // Use equality operator
            $args['filter[value][0]'] = strval($target_rating); // Convert to string for API

            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("API Filter applied: filter[field][0]=[question({$rating_question_id})]&filter[operator][0]==&filter[value][0]={$target_rating}");
            }
        }
        
        // Initialize collection of valid responses
        $valid_responses = array();
        $current_page = 1;
        $max_pages = 50; // Safety limit to prevent infinite loops - increased to handle more pagination
        $skipped_no_content = 0;
        $skipped_wrong_rating = 0;
        
        // Continue paginating until we have EXACTLY the requested number of valid reviews or reach the end
        while (count($valid_responses) < $max_reviews && $current_page <= $max_pages) {
            // Set current page in args
            $args['page'] = $current_page;
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("Fetching page {$current_page} of results");
                $param_string = '';
                foreach ($args as $key => $value) {
                    $param_string .= "{$key}={$value}, ";
                }
                error_log("Request parameters: " . $param_string);
            }
            
            // Make API request through get_survey_responses
            $response = $this->get_survey_responses($args);
            
            if (!$response['success']) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log("API request failed on page {$current_page}: " . $response['message']);
                }
                break; // Exit loop on error, return what we have so far
            }
            
            // Check if we got any data
            if (empty($response['data'])) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log("No responses found on page {$current_page}");
                }
                break; // No more responses available
            }
            
            // Process each response and verify it has BOTH the correct rating AND non-empty content
            $valid_page_responses = 0;
            foreach ($response['data'] as $response_item) {
                // Skip if we already have enough valid responses
                if (count($valid_responses) >= $max_reviews) {
                    break;
                }
                
                $is_valid = true;
                
                // Verify rating if we're filtering by rating
                if ($target_rating > 0 && !empty($rating_question_id)) {
                    $actual_rating = $this->extract_rating_from_response($response_item, $rating_question_id);
                    
                    if ($actual_rating !== $target_rating) {
                        if (defined('WP_DEBUG') && WP_DEBUG) {
                            $resp_id = isset($response_item['id']) ? $response_item['id'] : 'unknown';
                            error_log("Response {$resp_id} has rating {$actual_rating}, but we need {$target_rating}. Skipping.");
                        }
                        $is_valid = false;
                        $skipped_wrong_rating++;
                    }
                }
                
                // If rating is valid, check for content
                if ($is_valid) {
                    $has_content = $this->response_has_content($response_item, $rating_question_id);
                    
                    if (!$has_content) {
                        if (defined('WP_DEBUG') && WP_DEBUG) {
                            $resp_id = isset($response_item['id']) ? $response_item['id'] : 'unknown';
                            error_log("Response {$resp_id} has correct rating but no review content. Skipping.");
                        }
                        $is_valid = false;
                        $skipped_no_content++;
                    }
                }
                
                // Add to valid responses if it passes our checks
                if ($is_valid) {
                    $valid_responses[] = $response_item;
                    $valid_page_responses++;
                }
            }
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("Added {$valid_page_responses} valid responses from page {$current_page}");
                error_log("Total valid responses so far: " . count($valid_responses) . " of {$max_reviews} requested");
                error_log("Skipped on this page: " . ($skipped_wrong_rating + $skipped_no_content) . 
                         " (" . $skipped_wrong_rating . " wrong rating, " . $skipped_no_content . " no content)");
            }
            
            // Check if we've reached the last page of results
            if (!isset($response['total_pages']) || $current_page >= $response['total_pages']) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log("Reached the last page of results");
                }
                break;
            }
            
            // Check if we already have exactly the number of requested valid reviews
            if (count($valid_responses) >= $max_reviews) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log("Collected exactly {$max_reviews} valid reviews, stopping pagination");
                }
                break;
            }
            
            // Move to next page
            $current_page++;
        }
        
        // Trim to exact number if we somehow got more
        if (count($valid_responses) > $max_reviews) {
            $valid_responses = array_slice($valid_responses, 0, $max_reviews);
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("Trimmed result set to exactly {$max_reviews} valid reviews as requested");
            }
        }
        
        // For logging
        $valid_count = count($valid_responses);
        $pages_fetched = $current_page;
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("===== SEARCH COMPLETE =====");
            error_log("Found {$valid_count} valid reviews" . ($target_rating > 0 ? " with rating {$target_rating}" : ""));
            error_log("Pages fetched: {$pages_fetched}");
            error_log("Total skipped: " . ($skipped_wrong_rating + $skipped_no_content) . 
                     " (" . $skipped_wrong_rating . " wrong rating, " . $skipped_no_content . " no content)");
            if ($valid_count < $max_reviews) {
                error_log("Found fewer valid reviews than requested ({$valid_count} < {$max_reviews})");
                error_log("This likely means there aren't {$max_reviews} reviews with rating {$target_rating} and valid content available");
            }
            error_log("============================");
        }
        
        // Prepare result message
        $message = '';
        if ($target_rating > 0) {
            $message = sprintf(
                __('Found %d valid responses matching the rating criteria of %d stars', 'alchemer-reviews'), 
                $valid_count, 
                $target_rating
            );
        } else {
            $message = sprintf(__('Successfully retrieved %d valid survey responses', 'alchemer-reviews'), $valid_count);
        }
        
        // Add pagination info
        if ($pages_fetched > 1) {
            $message .= ' ' . sprintf(
                __('(fetched from %d pages)', 'alchemer-reviews'),
                $pages_fetched
            );
        }
        
        // Add note if we couldn't find enough reviews
        if ($valid_count < $max_reviews) {
            $message .= ' ' . sprintf(
                __('(Note: Requested %d reviews but only found %d matching the criteria)', 'alchemer-reviews'),
                $max_reviews,
                $valid_count
            );
        }
        
        // Add skipped info
        if ($skipped_no_content > 0) {
            $message .= ' ' . sprintf(
                __('(Skipped %d reviews with no content)', 'alchemer-reviews'),
                $skipped_no_content
            );
        }
        
        return array(
            'success' => true,
            'message' => $message,
            'data' => $valid_responses,
            'total' => $valid_count,
            'pages_fetched' => $pages_fetched,
            'max_reviews' => $max_reviews,
            'target_rating' => $target_rating,
            'skipped_no_content' => $skipped_no_content,
            'skipped_wrong_rating' => $skipped_wrong_rating
        );
    }
    
    /**
     * Extract rating value from a survey response
     * 
     * @param array $response Survey response data
     * @param string $rating_question_id ID of the rating question
     * @return int Rating value (0-5)
     */
    private function extract_rating_from_response($response, $rating_question_id) {
        // Check if the response data is in the expected format
        if (!isset($response['survey_data']) && is_array($response)) {
            // It might be directly in the response array
            $survey_data = $response;
        } else {
            $survey_data = isset($response['survey_data']) ? $response['survey_data'] : array();
        }
        
        // Debug
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Extracting rating from question ID: ' . $rating_question_id);
            
            // Log the structure of this response to help with debugging
            if (isset($response['id'])) {
                error_log('Response ID: ' . $response['id']);
            }
        }
        
        // Check if the rating question exists in the survey data
        if (!isset($survey_data[$rating_question_id])) {
            // Try alternate formats - sometimes the question ID might be a string or have a prefix
            foreach ($survey_data as $key => $value) {
                if (strval($key) === strval($rating_question_id) || 
                    strval($key) === 'question' . strval($rating_question_id)) {
                    $rating_question = $value;
                    
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        error_log('Found rating question under alternate key: ' . $key);
                    }
                    
                    return $this->parse_rating_value($rating_question);
                }
            }
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Rating question not found in response data. Available keys: ' . implode(', ', array_keys($survey_data)));
            }
            
            return 0;
        }
        
        // Get the rating question data
        $rating_question = $survey_data[$rating_question_id];
        
        return $this->parse_rating_value($rating_question);
    }
    
    /**
     * Parse rating value from question data
     * 
     * @param mixed $rating_question The rating question data
     * @return int Rating value (0-5)
     */
    private function parse_rating_value($rating_question) {
        // Initialize rating
        $rating = 0;
        
        // For debugging
        $method_used = 'none';
        $raw_value = 'undefined';
        
        // Handle different formats of the rating data
        if (is_numeric($rating_question)) {
            // Directly numeric
            $rating = intval($rating_question);
            $method_used = 'direct_numeric';
            $raw_value = $rating_question;
        } elseif (is_string($rating_question) && is_numeric($rating_question)) {
            // String that's numeric
            $rating = intval($rating_question);
            $method_used = 'string_numeric';
            $raw_value = $rating_question;
        } elseif (is_array($rating_question)) {
            // Various array formats
            if (isset($rating_question['answer']) && !empty($rating_question['answer'])) {
                // Direct answer field
                $raw_value = $rating_question['answer'];
                if (is_numeric($rating_question['answer'])) {
                    $rating = intval($rating_question['answer']);
                    $method_used = 'answer_field';
                } elseif (is_string($rating_question['answer']) && stripos($rating_question['answer'], 'star') !== false) {
                    // Handle text like "5 stars"
                    preg_match('/(\d+)\s*star/i', $rating_question['answer'], $matches);
                    if (!empty($matches[1])) {
                        $rating = intval($matches[1]);
                        $method_used = 'text_stars';
                    }
                }
            } elseif (isset($rating_question['answer_id']) && !empty($rating_question['answer_id'])) {
                // Answer ID field
                $raw_value = $rating_question['answer_id'];
                if (is_numeric($rating_question['answer_id'])) {
                    $rating = intval($rating_question['answer_id']);
                    $method_used = 'answer_id_field';
                }
            } elseif (isset($rating_question['options']) && is_array($rating_question['options'])) {
                // Options array with selected option
                $method_used = 'options_array';
                foreach ($rating_question['options'] as $option) {
                    if (isset($option['selected']) && $option['selected']) {
                        // Get the rating from selected option
                        if (isset($option['value']) && !empty($option['value'])) {
                            $raw_value = $option['value'];
                            if (is_numeric($option['value'])) {
                                $rating = intval($option['value']);
                                $method_used .= '_value';
                                break;
                            }
                        } elseif (isset($option['id']) && !empty($option['id'])) {
                            // Some APIs use the option ID for ratings
                            $raw_value = $option['id'];
                            if (is_numeric($option['id'])) {
                                $rating = intval($option['id']);
                                $method_used .= '_id';
                                break;
                            } elseif (isset($option['title']) && !empty($option['title'])) {
                                // Try to extract rating from title field (often contains "5 stars" or similar)
                                $raw_value = $option['title'];
                                if (is_string($option['title'])) {
                                    preg_match('/(\d+)\s*star/i', $option['title'], $matches);
                                    if (!empty($matches[1])) {
                                        $rating = intval($matches[1]);
                                        $method_used .= '_title_stars';
                                        break;
                                    }
                                }
                            }
                        }
                    }
                }
            } elseif (isset($rating_question['subquestions']) && is_array($rating_question['subquestions'])) {
                // For complex question types with subquestions
                $method_used = 'subquestions';
                foreach ($rating_question['subquestions'] as $subq) {
                    if (isset($subq['answer']) && !empty($subq['answer'])) {
                        $raw_value = $subq['answer'];
                        if (is_numeric($subq['answer'])) {
                            $rating = intval($subq['answer']);
                            $method_used .= '_answer';
                            break;
                        }
                    }
                }
            } elseif (isset($rating_question['question_pipe']) && isset($rating_question['shown'])) {
                // Sometimes rating is in sub-properties
                $method_used = 'question_pipe';
                if (isset($rating_question['value']) && !empty($rating_question['value'])) {
                    $raw_value = $rating_question['value'];
                    if (is_numeric($rating_question['value'])) {
                        $rating = intval($rating_question['value']);
                        $method_used .= '_value';
                    }
                }
            }
            
            // Last resort - look for any property that could be a rating value
            if ($rating === 0) {
                $possible_keys = ['value', 'rating', 'score', 'option_id', 'selected_option', 'id', 'option'];
                $method_used = 'fallback';
                foreach ($possible_keys as $possible_key) {
                    if (isset($rating_question[$possible_key]) && !empty($rating_question[$possible_key])) {
                        $raw_value = $rating_question[$possible_key];
                        if (is_numeric($rating_question[$possible_key])) {
                            $rating = intval($rating_question[$possible_key]);
                            $method_used .= '_' . $possible_key;
                            break;
                        } elseif (is_string($rating_question[$possible_key]) && 
                                 stripos($rating_question[$possible_key], 'star') !== false) {
                            // Handle text like "5 stars"
                            preg_match('/(\d+)\s*star/i', $rating_question[$possible_key], $matches);
                            if (!empty($matches[1])) {
                                $rating = intval($matches[1]);
                                $method_used .= '_text_stars';
                                break;
                            }
                        }
                    }
                }
            }
        }
        
        // Special case: handle rating scales that might be 0-4 but we want to treat as 1-5
        if ($rating >= 0 && $rating <= 4 && isset($rating_question['properties']['scale_min']) && 
            isset($rating_question['properties']['scale_max'])) {
            $scale_min = intval($rating_question['properties']['scale_min']);
            $scale_max = intval($rating_question['properties']['scale_max']);
            
            if ($scale_min === 0 && $scale_max === 4) {
                // This is a 0-4 scale, so we need to add 1 to get 1-5
                $old_rating = $rating;
                $rating = $rating + 1;
                $method_used .= '_scaled_0_4_to_1_5';
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log("Scaled rating from {$old_rating} to {$rating} (0-4 scale to 1-5)");
                }
            }
        }
        
        // Validate rating (most rating scales are 1-5 or 1-10)
        if ($rating < 0) {
            $rating = 0;
        }
        
        // Log the extracted rating
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("Extracted rating value: {$rating} (Method: {$method_used}, Raw: " . (is_string($raw_value) || is_numeric($raw_value) ? $raw_value : gettype($raw_value)) . ")");
        }
        
        return $rating;
    }
    
    /**
     * Check if a response has review content
     * 
     * @param array $response The survey response
     * @param string $rating_question_id ID of the rating question
     * @return bool True if there is content, false otherwise
     */
    private function response_has_content($response, $rating_question_id) {
        // Check if the response data is in the expected format
        if (!isset($response['survey_data']) && is_array($response)) {
            // It might be directly in the response array
            $survey_data = $response;
        } else {
            $survey_data = isset($response['survey_data']) ? $response['survey_data'] : array();
        }
        
        // Debug
        if (defined('WP_DEBUG') && WP_DEBUG) {
            if (isset($response['id'])) {
                error_log('Checking content for response ID: ' . $response['id']);
            }
        }
        
        // Check if the rating question exists in the survey data
        if (!isset($survey_data[$rating_question_id])) {
            // Try alternate formats - sometimes the question ID might be a string or have a prefix
            foreach ($survey_data as $key => $value) {
                if (strval($key) === strval($rating_question_id) || 
                    strval($key) === 'question' . strval($rating_question_id)) {
                    return $this->extract_content_from_question($value);
                }
            }
            
            return false;
        }
        
        // Get the rating question data
        $rating_question = $survey_data[$rating_question_id];
        
        return $this->extract_content_from_question($rating_question);
    }
    
    /**
     * Extract review content from a question
     * 
     * @param mixed $rating_question The question data
     * @return bool True if non-empty content was found
     */
    private function extract_content_from_question($rating_question) {
        // Extract the review content from the comments
        $content = '';
        
        if (isset($rating_question['comments'])) {
            $content = $rating_question['comments'];
        } elseif (isset($rating_question['comment'])) {
            $content = $rating_question['comment'];
        } elseif (isset($rating_question['custom_variable']) && !empty($rating_question['custom_variable'])) {
            $content = $rating_question['custom_variable'];
        }
        
        // If still no content, look for it in other possible fields
        if (empty($content) && is_array($rating_question)) {
            // Look for text fields that might contain review content
            foreach ($rating_question as $key => $value) {
                if (is_string($value) && strlen($value) > 10 && $key !== 'question') {
                    $content = $value;
                    break;
                }
            }
        }
        
        // Debug
        if (defined('WP_DEBUG') && WP_DEBUG) {
            if (empty($content)) {
                error_log('No content found in question');
            } else {
                error_log('Found content: ' . substr($content, 0, 30) . (strlen($content) > 30 ? '...' : ''));
            }
        }
        
        return !empty($content);
    }
} 