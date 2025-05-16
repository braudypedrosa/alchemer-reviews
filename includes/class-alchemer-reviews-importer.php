<?php
/**
 * Class for importing Alchemer survey responses as reviews
 *
 * @since 1.0.0
 */
class Alchemer_Reviews_Importer {

    /**
     * API instance
     *
     * @var Alchemer_Reviews_API
     */
    private $api;

    /**
     * Settings instance
     *
     * @var Alchemer_Reviews_Settings
     */
    private $settings;

    /**
     * Default field mappings
     *
     * @var array
     */
    private $default_field_mappings = array(
        'rating_question' => '',
        'reviewer_name' => '',
        'reviewer_email' => '',
        'review_date' => ''
    );

    /**
     * Constructor
     */
    public function __construct() {
        // Load dependencies
        require_once ALCHEMER_REVIEWS_PLUGIN_DIR . 'includes/class-alchemer-reviews-api.php';
        require_once ALCHEMER_REVIEWS_PLUGIN_DIR . 'includes/class-alchemer-reviews-settings.php';
        
        // Initialize objects
        $this->api = new Alchemer_Reviews_API();
        $this->settings = new Alchemer_Reviews_Settings();
    }

    /**
     * Initialize the class and set its hooks
     *
     * @return void
     */
    public function init() {
        // Add import button to the settings page
        add_action('alchemer_reviews_after_settings', array($this, 'render_import_button'));
        
        // Register AJAX handlers for importing reviews
        add_action('wp_ajax_import_alchemer_reviews', array($this, 'ajax_import_reviews'));
        add_action('wp_ajax_process_alchemer_review', array($this, 'ajax_process_review'));
        
        // Add admin menu for field mapping
        add_action('admin_menu', array($this, 'add_field_mapping_page'));
        
        // Register settings for field mappings
        add_action('admin_init', array($this, 'register_field_mapping_settings'));
        
        // Schedule daily import if auto-import is enabled
        add_action('alchemer_reviews_daily_import', array($this, 'import_reviews'));
        
        // Enqueue Tailwind for admin pages
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_styles'), 100);
    }

    /**
     * Enqueue Tailwind resources for admin pages
     *
     * @param string $hook The current admin page.
     * @return void
     */
    public function enqueue_admin_bootstrap($hook) {
        // Only load on our plugin pages
        if (!in_array($hook, array('review_page_alchemer_reviews_field_mapping', 'review_page_alchemer_reviews_settings'))) {
            return;
        }
        
        // Register and enqueue Tailwind CSS
        wp_register_style(
            'tailwind-alchemer',
            'https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css',
            array(),
            '2.2.19'
        );
        
        wp_enqueue_style('tailwind-alchemer');
        
        // Register and enqueue custom Tailwind admin CSS
        wp_register_style(
            'alchemer-tailwind-admin',
            ALCHEMER_REVIEWS_PLUGIN_URL . 'assets/css/admin-tailwind.css',
            array('tailwind-alchemer'),
            ALCHEMER_REVIEWS_VERSION
        );
        
        wp_enqueue_style('alchemer-tailwind-admin');
    }

    /**
     * Enqueue enhanced Tailwind styles with overrides for admin pages
     *
     * @param string $hook The current admin page.
     * @return void
     */
    public function enqueue_admin_styles($hook) {
        // Only load on our plugin pages
        if (!in_array($hook, array('review_page_alchemer_reviews_field_mapping', 'review_page_alchemer_reviews_settings'))) {
            return;
        }
        
        // Force dequeue conflicting WordPress styles for our admin pages
        wp_dequeue_style('wp-admin');
        wp_dequeue_style('wp-admin-css');
        
        // Register and enqueue Tailwind CSS
        wp_register_style(
            'tailwind-alchemer',
            'https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css',
            array(),
            '2.2.19'
        );
        
        wp_enqueue_style('tailwind-alchemer');
        
        // Register and enqueue custom Tailwind admin CSS
        wp_register_style(
            'alchemer-tailwind-admin',
            ALCHEMER_REVIEWS_PLUGIN_URL . 'assets/css/admin-tailwind.css',
            array('tailwind-alchemer'),
            ALCHEMER_REVIEWS_VERSION
        );
        
        wp_enqueue_style('alchemer-tailwind-admin');
        
        // Register and enqueue override CSS with highest specificity
        wp_register_style(
            'alchemer-tailwind-override',
            ALCHEMER_REVIEWS_PLUGIN_URL . 'assets/css/admin-tailwind-override.css',
            array('tailwind-alchemer', 'alchemer-tailwind-admin'),
            ALCHEMER_REVIEWS_VERSION . '.' . time() // Add timestamp to prevent caching
        );
        
        wp_enqueue_style('alchemer-tailwind-override');
    }

    /**
     * Add field mapping page to the Reviews menu
     *
     * @return void
     */
    public function add_field_mapping_page() {
        add_submenu_page(
            'edit.php?post_type=review',
            __('Tools', 'alchemer-reviews'),
            __('Tools', 'alchemer-reviews'),
            'manage_options',
            'alchemer_reviews_field_mapping',
            array($this, 'render_field_mapping_page')
        );
    }

    /**
     * Register settings for field mappings
     *
     * @return void
     */
    public function register_field_mapping_settings() {
        register_setting(
            'alchemer_reviews_field_mappings',
            'alchemer_reviews_field_mappings',
            array($this, 'sanitize_field_mappings')
        );

        add_settings_section(
            'alchemer_field_mappings',
            __('Map Survey Questions to Review Fields', 'alchemer-reviews'),
            array($this, 'render_field_mappings_section'),
            'alchemer_reviews_field_mapping'
        );

        // Add field mapping fields
        add_settings_field(
            'rating_question_field',
            __('Rating Question', 'alchemer-reviews'),
            array($this, 'render_rating_question_field'),
            'alchemer_reviews_field_mapping',
            'alchemer_field_mappings'
        );

        add_settings_field(
            'reviewer_name_field',
            __('Reviewer Name', 'alchemer-reviews'),
            array($this, 'render_reviewer_name_field'),
            'alchemer_reviews_field_mapping',
            'alchemer_field_mappings'
        );

        add_settings_field(
            'auto_import',
            __('Auto Import', 'alchemer-reviews'),
            array($this, 'render_auto_import_field'),
            'alchemer_reviews_field_mapping',
            'alchemer_field_mappings'
        );
    }

    /**
     * Sanitize field mappings
     *
     * @param array $input The field mappings input.
     * @return array
     */
    public function sanitize_field_mappings($input) {
        $sanitized_input = array();

        foreach ($this->default_field_mappings as $field => $default) {
            if (isset($input[$field])) {
                $sanitized_input[$field] = sanitize_text_field($input[$field]);
            } else {
                $sanitized_input[$field] = $default;
            }
        }

        // Handle auto-import setting
        if (isset($input['auto_import'])) {
            $sanitized_input['auto_import'] = (bool) $input['auto_import'];
            
            // Schedule or unschedule the daily import event
            if ($sanitized_input['auto_import']) {
                if (!wp_next_scheduled('alchemer_reviews_daily_import')) {
                    wp_schedule_event(time(), 'daily', 'alchemer_reviews_daily_import');
                }
            } else {
                wp_clear_scheduled_hook('alchemer_reviews_daily_import');
            }
        } else {
            $sanitized_input['auto_import'] = false;
            wp_clear_scheduled_hook('alchemer_reviews_daily_import');
        }

        return $sanitized_input;
    }

    /**
     * Render field mappings section
     *
     * @return void
     */
    public function render_field_mappings_section() {
        echo '<p class="text-gray-600 text-lg mb-6 w-full">' . __('Map your Alchemer survey questions to the review fields. You need to enter the question IDs from your survey.', 'alchemer-reviews') . '</p>';
        
        // Fetch and display available survey questions if possible
        $survey_questions = $this->get_survey_questions();
        if (!empty($survey_questions)) {
            echo '<div class="dashboard-card w-full">';
            echo '<h3 class="text-lg font-medium text-gray-700 mb-3 flex items-center">';
            echo '<span class="dashicons dashicons-list-view mr-2"></span> ' . __('Available Survey Questions', 'alchemer-reviews');
            echo '</h3>';
            echo '<p class="form-help-text mb-4">' . __('Use the question IDs below for your field mappings.', 'alchemer-reviews') . '</p>';
            echo '<div class="overflow-x-auto">';
            echo '<table class="alchemer-table">';
            echo '<thead><tr><th class="w-24">' . __('Question ID', 'alchemer-reviews') . '</th><th>' . __('Question Text', 'alchemer-reviews') . '</th></tr></thead>';
            echo '<tbody>';
            
            foreach ($survey_questions as $id => $text) {
                echo '<tr>';
                echo '<td><code class="bg-gray-100 px-2 py-1 rounded">' . esc_html($id) . '</code></td>';
                echo '<td>' . esc_html($text) . '</td>';
                echo '</tr>';
            }
            
            echo '</tbody></table>';
            echo '</div>';
            echo '</div>';
        }
    }

    /**
     * Render rating question field
     *
     * @return void
     */
    public function render_rating_question_field() {
        $options = $this->get_field_mappings();
        $value = isset($options['rating_question']) ? $options['rating_question'] : '';
        
        echo '<div class="form-input-container w-full">';
        echo '<input type="text" id="rating_question_field" name="alchemer_reviews_field_mappings[rating_question]" value="' . esc_attr($value) . '" class="form-input">';
        echo '<div class="form-help-text">' . __('Enter the ID of the rating question (e.g., "83"). This is the question that contains both the rating value and the review comments.', 'alchemer-reviews') . '</div>';
        echo '</div>';
    }

    /**
     * Render reviewer name field
     *
     * @return void
     */
    public function render_reviewer_name_field() {
        $options = $this->get_field_mappings();
        $value = isset($options['reviewer_name']) ? $options['reviewer_name'] : '';
        
        echo '<div class="form-input-container w-full">';
        echo '<input type="text" id="reviewer_name_field" name="alchemer_reviews_field_mappings[reviewer_name]" value="' . esc_attr($value) . '" class="form-input">';
        echo '<div class="form-help-text">' . __('Enter the question ID for the reviewer\'s name.', 'alchemer-reviews') . '</div>';
        echo '</div>';
    }

    /**
     * Render auto import field
     *
     * @return void
     */
    public function render_auto_import_field() {
        $options = $this->get_field_mappings();
        $checked = isset($options['auto_import']) && $options['auto_import'] ? 'checked' : '';
        
        echo '<div class="form-input-container w-full">';
        echo '<div class="flex items-center">';
        echo '<input class="h-4 w-4 text-blue-600 rounded border-gray-300 focus:ring-blue-500" type="checkbox" id="auto_import" name="alchemer_reviews_field_mappings[auto_import]" value="1" ' . $checked . '>';
        echo '<label class="ml-2 block text-sm text-gray-700" for="auto_import">' . __('Automatically import new reviews daily', 'alchemer-reviews') . '</label>';
        echo '</div>';
        echo '<div class="form-help-text">' . __('When enabled, the plugin will check for new survey responses daily and import them as reviews.', 'alchemer-reviews') . '</div>';
        echo '</div>';
    }

    /**
     * Render field mapping page
     *
     * @return void
     */
    public function render_field_mapping_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        // Get current tab
        $current_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'field_mapping';
        
        ?>
        <div class="wrap">
            <div class="alchemer-admin-area w-full p-6">
                <h1 class="text-2xl font-bold mb-6"><?php echo esc_html(get_admin_page_title()); ?></h1>
                
                <?php
                // Display connection status
                $settings = $this->settings->get_settings();
                if (empty($settings['api_token']) || empty($settings['api_token_secret']) || empty($settings['survey_id'])) {
                    ?>
                    <div class="alert alert-warning flex items-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 mr-3 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                        <div>
                            <?php _e('API connection is not configured. Please go to the Settings page to set up your Alchemer API connection first.', 'alchemer-reviews'); ?>
                        </div>
                    </div>
                    <?php
                }
                ?>
                
                <div class="tab-nav mb-8">
                    <a href="<?php echo admin_url('edit.php?post_type=review&page=alchemer_reviews_field_mapping&tab=field_mapping'); ?>" 
                       class="tab-link <?php echo $current_tab === 'field_mapping' ? 'active' : ''; ?>">
                        <span class="dashicons dashicons-editor-table mr-1"></span>
                        <?php _e('Field Mapping', 'alchemer-reviews'); ?>
                    </a>
                    <a href="<?php echo admin_url('edit.php?post_type=review&page=alchemer_reviews_field_mapping&tab=import_reviews'); ?>" 
                       class="tab-link <?php echo $current_tab === 'import_reviews' ? 'active' : ''; ?>">
                        <span class="dashicons dashicons-download mr-1"></span>
                        <?php _e('Import Reviews', 'alchemer-reviews'); ?>
                    </a>
                </div>

                <?php if ($current_tab === 'field_mapping'): ?>
                
                <div class="dashboard-card w-full fade-in">
                    <h2 class="text-xl font-medium text-gray-800 mb-4">
                        <?php _e('Map Survey Questions to Review Fields', 'alchemer-reviews'); ?>
                    </h2>
                    <form action="options.php" method="post" class="w-full">
                        <?php
                        settings_fields('alchemer_reviews_field_mappings');
                        do_settings_sections('alchemer_reviews_field_mapping');
                        ?>
                        <div class="mt-6">
                            <button type="submit" class="alchemer-button alchemer-button-primary">
                                <?php _e('Save Field Mappings', 'alchemer-reviews'); ?>
                            </button>
                        </div>
                    </form>
                </div>
                
                <?php elseif ($current_tab === 'import_reviews'): ?>
                
                <div class="dashboard-card w-full fade-in">
                    <h2 class="text-xl font-medium text-gray-800 mb-4">
                        <?php _e('Import Reviews from Alchemer', 'alchemer-reviews'); ?>
                    </h2>
                    <p class="text-gray-600 mb-6">
                        <?php _e('Click the button below to manually import reviews from your Alchemer survey.', 'alchemer-reviews'); ?>
                    </p>
                    
                    <?php
                    $mappings = $this->get_field_mappings();
                    if (empty($mappings['rating_question'])) {
                        ?>
                        <div class="alert alert-warning">
                            <p class="mb-3"><?php _e('Please configure your field mappings before importing reviews.', 'alchemer-reviews'); ?></p>
                            <a href="<?php echo admin_url('edit.php?post_type=review&page=alchemer_reviews_field_mapping&tab=field_mapping'); ?>" 
                               class="alchemer-button alchemer-button-primary">
                                <span class="dashicons dashicons-editor-table mr-1"></span>
                                <?php _e('Configure Field Mappings', 'alchemer-reviews'); ?>
                            </a>
                        </div>
                        <?php
                    } else {
                        ?>
                        <div class="bg-gray-50 p-6 rounded-lg mb-6">
                            <h3 class="text-lg font-medium text-gray-700 mb-4">
                                <?php _e('Import Settings', 'alchemer-reviews'); ?>
                            </h3>
                            
                            <!-- Import Filters -->
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                                <!-- Maximum Reviews -->
                                <div>
                                    <label for="max-reviews" class="block text-sm font-medium text-gray-700 mb-1">
                                        <?php _e('Maximum Reviews to Import', 'alchemer-reviews'); ?>
                                    </label>
                                    <div class="flex items-center">
                                        <input type="number" id="max-reviews" name="max_reviews" min="1" max="100" value="20" 
                                               class="form-input block w-full sm:text-sm rounded-md" />
                                        <div class="ml-2 text-sm text-gray-500">
                                            <?php _e('(1-100)', 'alchemer-reviews'); ?>
                                        </div>
                                    </div>
                                    <p class="text-xs text-gray-500 mt-1">
                                        <?php _e('Limit the number of reviews to import in this batch.', 'alchemer-reviews'); ?>
                                    </p>
                                </div>
                                
                                <!-- Filter by Rating -->
                                <div>
                                    <label for="target-rating" class="block text-sm font-medium text-gray-700 mb-1">
                                        <?php _e('Filter by Rating', 'alchemer-reviews'); ?>
                                    </label>
                                    <div class="flex items-center">
                                        <select id="target-rating" name="target_rating" class="form-input block w-full sm:text-sm rounded-md">
                                            <option value="0"><?php _e('All Ratings', 'alchemer-reviews'); ?></option>
                                            <option value="5">★★★★★ (5 <?php _e('stars only', 'alchemer-reviews'); ?>)</option>
                                            <option value="4">★★★★☆ (4 <?php _e('stars only', 'alchemer-reviews'); ?>)</option>
                                            <option value="3">★★★☆☆ (3 <?php _e('stars only', 'alchemer-reviews'); ?>)</option>
                                            <option value="2">★★☆☆☆ (2 <?php _e('stars only', 'alchemer-reviews'); ?>)</option>
                                            <option value="1">★☆☆☆☆ (1 <?php _e('star only', 'alchemer-reviews'); ?>)</option>
                                        </select>
                                    </div>
                                    <p class="text-xs text-gray-500 mt-1">
                                        <?php _e('Optionally filter reviews by specific rating. The importer will search multiple pages if needed to find your target number of reviews.', 'alchemer-reviews'); ?>
                                    </p>
                                </div>
                            </div>
                            
                            <!-- Import Button -->
                            <div class="text-center mt-6">
                                <button type="button" id="import-alchemer-reviews" class="alchemer-button alchemer-button-primary alchemer-button-lg">
                                    <span class="dashicons dashicons-download mr-1"></span>
                                    <?php _e('Import Reviews Now', 'alchemer-reviews'); ?>
                                </button>
                                <div class="spinner mt-4 hidden" id="import-spinner"></div>
                            </div>
                        </div>
                        
                        <div id="import-result" class="hidden">
                            <!-- Results will be loaded here via JavaScript -->
                        </div>
                        <?php
                    }
                    ?>
                </div>
                
                <!-- Auto Import Section -->
                <div class="dashboard-card w-full mt-6 fade-in">
                    <h2 class="text-xl font-medium text-gray-800 mb-4">
                        <?php _e('Automatic Import Settings', 'alchemer-reviews'); ?>
                    </h2>
                    <form action="options.php" method="post" class="w-full">
                        <?php
                        settings_fields('alchemer_reviews_field_mappings');
                        $options = $this->get_field_mappings();
                        $checked = isset($options['auto_import']) && $options['auto_import'] ? 'checked' : '';
                        ?>
                        
                        <div class="flex items-center mb-4">
                            <input class="h-4 w-4 text-blue-600 rounded border-gray-300 focus:ring-blue-500" 
                                   type="checkbox" id="auto_import_option" 
                                   name="alchemer_reviews_field_mappings[auto_import]" 
                                   value="1" <?php echo $checked; ?>>
                            <label class="ml-2 text-gray-700" for="auto_import_option">
                                <?php _e('Automatically import new reviews daily', 'alchemer-reviews'); ?>
                            </label>
                        </div>
                        <p class="text-sm text-gray-500 mb-4">
                            <?php _e('When enabled, the plugin will check for new survey responses daily and import them as reviews.', 'alchemer-reviews'); ?>
                        </p>
                        
                        <button type="submit" class="alchemer-button alchemer-button-secondary">
                            <?php _e('Save Auto-Import Setting', 'alchemer-reviews'); ?>
                        </button>
                    </form>
                </div>
                
                <?php endif; ?>
            </div>
        </div>
        
        <?php
        // Enqueue the admin script
        wp_enqueue_script(
            'alchemer-reviews-importer',
            ALCHEMER_REVIEWS_PLUGIN_URL . 'assets/js/importer.js',
            array('jquery'),
            ALCHEMER_REVIEWS_VERSION,
            true
        );
        
        wp_localize_script(
            'alchemer-reviews-importer',
            'alchemerReviewsImporter',
            array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('import_alchemer_reviews'),
                'importingText' => __('Importing reviews from Alchemer...', 'alchemer-reviews'),
                'errorText' => __('Error: ', 'alchemer-reviews'),
                'createdText' => __('New Reviews', 'alchemer-reviews'),
                'updatedText' => __('Updated', 'alchemer-reviews'),
                'skippedText' => __('Skipped', 'alchemer-reviews'),
                'starsOnlyText' => __('stars only', 'alchemer-reviews'),
                'filteredText' => __('Filtered by rating', 'alchemer-reviews'),
            )
        );

        // Enqueue import-specific CSS
        wp_enqueue_style(
            'alchemer-import-styles',
            ALCHEMER_REVIEWS_PLUGIN_URL . 'assets/css/admin-import.css',
            array(),
            ALCHEMER_REVIEWS_VERSION . '.' . time()
        );
    }

    /**
     * Render import button on settings page
     *
     * @return void
     */
    public function render_import_button() {
        ?>
        <div class="dashboard-card w-full mt-6 fade-in">
            <h2 class="text-xl font-medium text-gray-800 mb-4">
                <?php _e('Import Reviews', 'alchemer-reviews'); ?>
            </h2>
            <p class="text-gray-600 mb-4">
                <?php _e('Go to the Tools section to import reviews from your Alchemer survey.', 'alchemer-reviews'); ?>
            </p>
            
            <div class="flex items-center mt-4">
                <a href="<?php echo admin_url('edit.php?post_type=review&page=alchemer_reviews_field_mapping&tab=import_reviews'); ?>" 
                   class="alchemer-button alchemer-button-primary">
                    <span class="dashicons dashicons-download mr-1"></span>
                    <?php _e('Go to Import Reviews', 'alchemer-reviews'); ?>
                </a>
            </div>
        </div>
        <?php
    }

    /**
     * AJAX handler for importing reviews
     *
     * @return void
     */
    public function ajax_import_reviews() {
        // Check nonce
        check_ajax_referer('import_alchemer_reviews', 'nonce');
        
        // Check user capability
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array(
                'message' => __('You do not have permission to perform this action.', 'alchemer-reviews'),
            ));
        }
        
        // Get import parameters
        $max_reviews = isset($_POST['max_reviews']) ? intval($_POST['max_reviews']) : 20;
        $target_rating = isset($_POST['target_rating']) ? intval($_POST['target_rating']) : 0;
        
        // Prepare import arguments
        $import_args = array(
            'max_reviews' => $max_reviews,
            'target_rating' => $target_rating,
        );
        
        // Import reviews
        $result = $this->import_reviews($import_args);
        
        if ($result['success']) {
            wp_send_json_success(array(
                'message' => $result['message'],
                'reviews' => $result['reviews'],
                'total_found' => $result['total_found'],
                'skipped' => $result['skipped']
            ));
        } else {
            wp_send_json_error(array(
                'message' => $result['message'],
            ));
        }
    }

    /**
     * AJAX handler for processing a single review
     *
     * @return void
     */
    public function ajax_process_review() {
        // Check nonce
        check_ajax_referer('import_alchemer_reviews', 'nonce');
        
        // Check user capability
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array(
                'message' => __('You do not have permission to perform this action.', 'alchemer-reviews'),
            ));
        }
        
        // Get review data
        $review_data = isset($_POST['review_data']) ? $_POST['review_data'] : array();
        $accept = isset($_POST['accept']) ? (bool) $_POST['accept'] : false;
        
        if (empty($review_data)) {
            wp_send_json_error(array(
                'message' => __('No review data provided.', 'alchemer-reviews'),
            ));
        }
        
        // Process the review
        $result = $this->process_review($review_data, $accept);
        
        if ($result['success']) {
            wp_send_json_success(array(
                'message' => sprintf(
                    __('Review %s successfully.', 'alchemer-reviews'),
                    $accept ? 'accepted and published' : 'rejected and saved as draft'
                ),
                'post_id' => $result['post_id'],
                'status' => $result['status']
            ));
        } else {
            wp_send_json_error(array(
                'message' => $result['message'],
            ));
        }
    }

    /**
     * Import reviews from Alchemer
     *
     * @param array $import_args Arguments for importing
     * @return array Result with success status, message and count info
     */
    public function import_reviews($import_args = array()) {
        // Check if API settings are configured
        $settings = $this->settings->get_settings();
        if (empty($settings['api_token']) || empty($settings['api_token_secret']) || empty($settings['survey_id'])) {
            return array(
                'success' => false,
                'message' => __('API connection is not configured. Please go to the Settings page to set up your Alchemer API connection.', 'alchemer-reviews'),
                'imported_count' => 0,
            );
        }
        
        // Check if field mappings are configured
        $mappings = $this->get_field_mappings();
        if (empty($mappings['rating_question'])) {
            return array(
                'success' => false,
                'message' => __('Field mappings are not configured. Please go to the Field Mapping page to set up your field mappings.', 'alchemer-reviews'),
                'imported_count' => 0,
            );
        }
        
        // Default import arguments
        $default_args = array(
            'max_reviews' => 20,
            'target_rating' => 0, // 0 means all ratings
        );
        
        // Merge with provided arguments
        $import_args = wp_parse_args($import_args, $default_args);
        
        // Extract import arguments
        $max_reviews = intval($import_args['max_reviews']);
        $target_rating = intval($import_args['target_rating']);
        
        // Initialize counters
        $created_count = 0;
        $updated_count = 0;
        $skipped_count = 0;
        $skipped_edited_count = 0;
        $errors = array();
        
        // Get the responses from the API
        $responses = $this->api->get_filtered_responses(array(), $max_reviews, $target_rating);
        
        if (!$responses['success']) {
            return array(
                'success' => false,
                'message' => $responses['message'],
                'imported_count' => 0,
            );
        }
        
        if (empty($responses['data'])) {
            return array(
                'success' => true,
                'message' => __('No new responses found to import.', 'alchemer-reviews'),
                'imported_count' => 0,
            );
        }

        // Process responses and prepare for review
        $reviews_to_process = array();
        foreach ($responses['data'] as $response) {
            // Get basic review data
            $review_data = $this->prepare_review_data($response);
            if (!$review_data['success']) {
                $skipped_count++;
                continue;
            }

            // Get AI analysis
            $ai_analysis = $this->get_gemini_sentiment_and_suggestion($review_data['content']);
            
            // Add to reviews to process
            $reviews_to_process[] = array(
                'response' => $response,
                'review_data' => $review_data,
                'ai_analysis' => $ai_analysis
            );
        }

        // Return the reviews for processing
        return array(
            'success' => true,
            'message' => sprintf(__('Found %d reviews to process.', 'alchemer-reviews'), count($reviews_to_process)),
            'reviews' => $reviews_to_process,
            'total_found' => count($reviews_to_process),
            'skipped' => $skipped_count
        );
    }

    /**
     * Prepare review data from response
     *
     * @param array $response Survey response data
     * @return array Review data
     */
    private function prepare_review_data($response) {
        // Get field mappings
        $mappings = $this->get_field_mappings();
        $rating_question_id = $mappings['rating_question'];
        $reviewer_name_field = $mappings['reviewer_name'];
        
        // Get survey data
        $survey_data = isset($response['survey_data']) ? $response['survey_data'] : $response;
        
        // Check if the rating question exists
        if (!isset($survey_data[$rating_question_id])) {
            return array(
                'success' => false,
                'message' => sprintf(__('Rating question (ID: %s) not found in survey response.', 'alchemer-reviews'), $rating_question_id)
            );
        }
        
        // Get rating question data
        $rating_question = $survey_data[$rating_question_id];
        
        // Extract rating
        $rating = 0;
        if (isset($rating_question['answer'])) {
            $rating = intval($rating_question['answer']);
        } elseif (isset($rating_question['answer_id'])) {
            $rating = intval($rating_question['answer_id']);
        }
        
        if ($rating <= 0) {
            return array(
                'success' => false,
                'message' => __('No rating value found', 'alchemer-reviews')
            );
        }
        
        // Extract content
        $content = '';
        if (isset($rating_question['comments'])) {
            $content = $rating_question['comments'];
        } elseif (isset($rating_question['comment'])) {
            $content = $rating_question['comment'];
        } elseif (isset($response['comments'])) {
            $content = $response['comments'];
        } elseif (isset($response['comment'])) {
            $content = $response['comment'];
        }
        
        if (empty($content)) {
            return array(
                'success' => false,
                'message' => __('No review content found', 'alchemer-reviews')
            );
        }
        
        // Get reviewer name
        $reviewer_name = __('Anonymous', 'alchemer-reviews');
        if (!empty($reviewer_name_field) && isset($survey_data[$reviewer_name_field])) {
            $reviewer_name_data = $survey_data[$reviewer_name_field];
            if (isset($reviewer_name_data['answer'])) {
                $reviewer_name = sanitize_text_field($reviewer_name_data['answer']);
            } elseif (is_string($reviewer_name_data)) {
                $reviewer_name = sanitize_text_field($reviewer_name_data);
            }
        }
        
        // Format reviewer name
        $reviewer_name = $this->format_name_proper_case($reviewer_name);
        
        // Get submission date
        $post_date = '';
        if (isset($response['date_submitted'])) {
            $post_date = date('Y-m-d H:i:s', strtotime($response['date_submitted']));
        } elseif (isset($response['datesubmitted'])) {
            $post_date = date('Y-m-d H:i:s', strtotime($response['datesubmitted']));
        } elseif (isset($response['submitdate'])) {
            $post_date = date('Y-m-d H:i:s', strtotime($response['submitdate']));
        } else {
            $post_date = current_time('mysql');
        }
        
        return array(
            'success' => true,
            'response_id' => isset($response['id']) ? $response['id'] : '',
            'reviewer_name' => $reviewer_name,
            'rating' => $rating,
            'content' => $content,
            'post_date' => $post_date
        );
    }

    /**
     * Process a single review with AI analysis, handling duplicates and skip overwrite
     *
     * @param array $review_data Review data including AI analysis
     * @param bool $accept Whether to accept the review
     * @return array Result of processing
     */
    public function process_review($review_data, $accept = false) {
        if (!isset($review_data['success']) || !$review_data['success']) {
            return array(
                'success' => false,
                'message' => isset($review_data['message']) ? $review_data['message'] : __('Invalid review data.', 'alchemer-reviews')
            );
        }

        // Check for existing review by response ID
        $existing = get_posts(array(
            'post_type' => 'review',
            'meta_key' => '_alchemer_response_id',
            'meta_value' => $review_data['response_id'],
            'posts_per_page' => 1,
            'post_status' => array('publish', 'draft', 'pending', 'future', 'private'),
            'fields' => 'ids',
        ));
        if ($existing) {
            $post_id = $existing[0];
            $skip_overwrite = get_post_meta($post_id, '_alchemer_manually_edited', true);
            if ($skip_overwrite === '1') {
                return array(
                    'success' => false,
                    'message' => __('Skipped: Review is set to skip overwrite.', 'alchemer-reviews')
                );
            }
            // Update the post
            $post_data = array(
                'ID' => $post_id,
                'post_title' => $review_data['reviewer_name'],
                'post_content' => $accept ? $review_data['ai_analysis']['suggestion'] : $review_data['content'],
                'post_status' => $accept ? 'publish' : 'draft',
                'post_date' => $review_data['post_date'],
            );
            $result = wp_update_post($post_data, true);
            if (is_wp_error($result)) {
                return array(
                    'success' => false,
                    'message' => $result->get_error_message()
                );
            }
            // Update meta
            update_post_meta($post_id, '_alchemer_reviewer_name', $review_data['reviewer_name']);
            update_post_meta($post_id, '_alchemer_rating', $review_data['rating']);
            update_post_meta($post_id, 'ai_sentiment', $review_data['ai_analysis']['sentiment']);
            update_post_meta($post_id, 'ai_suggestion', $review_data['ai_analysis']['suggestion']);
            update_post_meta($post_id, '_alchemer_manually_edited', '0');
            return array(
                'success' => true,
                'post_id' => $post_id,
                'status' => $accept ? 'published' : 'draft',
                'updated' => true
            );
        }

        // Prepare post data for new review
        $post_data = array(
            'post_title' => $review_data['reviewer_name'],
            'post_content' => $accept ? $review_data['ai_analysis']['suggestion'] : $review_data['content'],
            'post_status' => $accept ? 'publish' : 'draft',
            'post_type' => 'review',
            'post_date' => $review_data['post_date'],
        );
        $post_id = wp_insert_post($post_data);
        if (is_wp_error($post_id)) {
            return array(
                'success' => false,
                'message' => $post_id->get_error_message()
            );
        }
        // Save meta data
        update_post_meta($post_id, '_alchemer_response_id', $review_data['response_id']);
        update_post_meta($post_id, '_alchemer_reviewer_name', $review_data['reviewer_name']);
        update_post_meta($post_id, '_alchemer_rating', $review_data['rating']);
        update_post_meta($post_id, 'ai_sentiment', $review_data['ai_analysis']['sentiment']);
        update_post_meta($post_id, 'ai_suggestion', $review_data['ai_analysis']['suggestion']);
        update_post_meta($post_id, '_alchemer_manually_edited', '0');
        return array(
            'success' => true,
            'post_id' => $post_id,
            'status' => $accept ? 'published' : 'draft',
            'updated' => false
        );
    }

    /**
     * Format a name to proper case (first letter uppercase, rest lowercase)
     * 
     * @param string $name The name to format
     * @return string The formatted name
     */
    private function format_name_proper_case($name) {
        // If empty, return as is
        if (empty($name)) {
            return $name;
        }
        
        // Handle multi-word names
        $parts = explode(' ', $name);
        $formatted_parts = array();
        
        foreach ($parts as $part) {
            // Skip empty parts
            if (empty($part)) {
                continue;
            }
            
            // Handle hyphenated names like "Mary-Jane"
            if (strpos($part, '-') !== false) {
                $hyphen_parts = explode('-', $part);
                $formatted_hyphen_parts = array();
                
                foreach ($hyphen_parts as $h_part) {
                    if (!empty($h_part)) {
                        $formatted_hyphen_parts[] = ucfirst(strtolower($h_part));
                    }
                }
                
                $formatted_parts[] = implode('-', $formatted_hyphen_parts);
            } else {
                // Regular name part
                $formatted_parts[] = ucfirst(strtolower($part));
            }
        }
        
        return implode(' ', $formatted_parts);
    }

    /**
     * Call Gemini API for sentiment and suggestion
     *
     * @param string $content The review content
     * @return array [ 'sentiment' => 'Positive'|'Negative', 'suggestion' => string ]
     */
    private function get_gemini_sentiment_and_suggestion($content) {
        $api_key = getenv('GEMINI_API_KEY');
        if (empty($api_key) && isset($_ENV['GEMINI_API_KEY'])) {
            $api_key = $_ENV['GEMINI_API_KEY'];
        }
        if (empty($api_key)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Gemini API key not found in environment variables');
            }
            return [ 'sentiment' => 'Unknown', 'suggestion' => '' ];
        }

        $url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=' . $api_key;
        $prompt = "Analyze the following review. First, say if the tone is Positive or Negative. Then, suggest a single improved version of the review, keeping the same context and meaning.\nReview: {$content}";
        
        $body = json_encode([
            'contents' => [
                [ 'parts' => [ [ 'text' => $prompt ] ] ]
            ]
        ]);

        if ($body === false) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Failed to encode Gemini API request body: ' . json_last_error_msg());
            }
            return [ 'sentiment' => 'Unknown', 'suggestion' => '' ];
        }

        $response = wp_remote_post($url, [
            'headers' => [ 'Content-Type' => 'application/json' ],
            'body' => $body,
            'timeout' => 20,
        ]);

        if (is_wp_error($response)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Gemini API request failed: ' . $response->get_error_message());
            }
            return [ 'sentiment' => 'Unknown', 'suggestion' => '' ];
        }

        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Gemini API returned non-200 status code: ' . $response_code);
                error_log('Response body: ' . wp_remote_retrieve_body($response));
            }
            return [ 'sentiment' => 'Unknown', 'suggestion' => '' ];
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Failed to decode Gemini API response: ' . json_last_error_msg());
                error_log('Raw response: ' . $body);
            }
            return [ 'sentiment' => 'Unknown', 'suggestion' => '' ];
        }

        if (!isset($data['candidates'][0]['content']['parts'][0]['text'])) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Unexpected Gemini API response structure: ' . print_r($data, true));
            }
            return [ 'sentiment' => 'Unknown', 'suggestion' => '' ];
        }

        $text = $data['candidates'][0]['content']['parts'][0]['text'];
        
        // Parse sentiment and suggestion from the response
        $sentiment = 'Unknown';
        $suggestion = '';

        if ($text) {
            // Extract sentiment
            if (preg_match('/(Positive|Negative)/i', $text, $matches)) {
                $sentiment = ucfirst(strtolower($matches[1]));
            }

            // Extract suggestion
            $lines = preg_split('/\r?\n/', $text);
            foreach ($lines as $line) {
                if (stripos($line, 'suggestion:') !== false) {
                    $suggestion = trim(str_ireplace('suggestion:', '', $line));
                    break;
                }
            }

            // If no explicit suggestion found, use the last non-empty line
            if (empty($suggestion)) {
                $lines = array_filter($lines, 'trim');
                if (!empty($lines)) {
                    $suggestion = trim(end($lines));
                }
            }
        }

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Gemini API analysis - Sentiment: ' . $sentiment . ', Suggestion: ' . $suggestion);
        }

        return [ 'sentiment' => $sentiment, 'suggestion' => $suggestion ];
    }

    /**
     * Get survey questions from Alchemer
     *
     * @return array|bool Array of question IDs and texts, or false on error
     */
    private function get_survey_questions() {
        // Load API class
        $api = $this->api;
        
        // Fetch survey details
        $survey = $api->get_survey_details();
        
        if (!$survey['success'] || empty($survey['data']['pages'])) {
            return false;
        }
        
        $questions = array();
        
        // Extract questions from survey
        foreach ($survey['data']['pages'] as $page) {
            if (empty($page['questions'])) {
                continue;
            }
            
            foreach ($page['questions'] as $question) {
                $questions[$question['id']] = isset($question['title']) ? $question['title']['English'] : __('Untitled Question', 'alchemer-reviews');
            }
        }
        
        return $questions;
    }

    /**
     * Get field mappings from options
     *
     * @return array
     */
    private function get_field_mappings() {
        $mappings = get_option('alchemer_reviews_field_mappings', $this->default_field_mappings);
        return wp_parse_args($mappings, $this->default_field_mappings);
    }
} 