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
                'counts' => array(
                    'total' => isset($result['created_count']) ? $result['created_count'] + $result['updated_count'] : $result['imported_count'],
                    'created' => isset($result['created_count']) ? $result['created_count'] : 0,
                    'updated' => isset($result['updated_count']) ? $result['updated_count'] : 0,
                    'skipped_edited' => isset($result['skipped_edited_count']) ? $result['skipped_edited_count'] : 0,
                    'skipped' => isset($result['skipped_count']) ? $result['skipped_count'] : 0,
                ),
                'filters' => array(
                    'max_reviews' => $max_reviews,
                    'target_rating' => $target_rating,
                ),
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
        
        // Logging
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("Starting import process. Target: {$max_reviews} valid reviews with rating {$target_rating}");
        }
        
        // Get the responses from the API - these will already be filtered by rating and only contain valid responses
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
        
        // Process the valid responses - these all match the target rating already
        $all_responses = $responses['data'];
        $total_valid = 0;
        $pages_fetched = isset($responses['pages_fetched']) ? $responses['pages_fetched'] : 1;
        $found_responses_count = count($all_responses);
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("Received {$found_responses_count} valid responses from API that match criteria");
            error_log("Pages fetched from API: {$pages_fetched}");
        }
        
        // Process all valid responses
        foreach ($all_responses as $response) {
            // Try to create or update the review
            $result = $this->create_review_from_response($response);
            
            if ($result['success']) {
                if ($result['action'] === 'created') {
                    $created_count++;
                } else { // updated
                    $updated_count++;
                }
                
                // Count valid reviews (created or updated)
                $total_valid++;
            } else {
                // Check if it was skipped because it was manually edited
                if (strpos($result['message'], 'Skipping response ID') !== false && 
                    strpos($result['message'], 'manually edited') !== false) {
                    $skipped_edited_count++;
                } else {
                    $skipped_count++;
                    $errors[] = $result['message'];
                }
            }
        }
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("Import process complete. Processed valid reviews: {$total_valid}/{$found_responses_count}");
            error_log("Created: {$created_count}, Updated: {$updated_count}, Skipped (edited): {$skipped_edited_count}, Skipped (other): {$skipped_count}");
        }
        
        // Build result message
        if ($created_count > 0 || $updated_count > 0) {
            $message = sprintf(
                __('Successfully imported %d reviews (%d created, %d updated) from Alchemer.', 'alchemer-reviews'),
                $created_count + $updated_count,
                $created_count,
                $updated_count
            );
            
            // Add rating filter info if applicable
            if ($target_rating > 0) {
                $message .= ' ' . sprintf(__('Applied filter: %d-star ratings only.', 'alchemer-reviews'), $target_rating);
            }
            
            // Add info about fetched pages
            if ($pages_fetched > 1) {
                $message .= ' ' . sprintf(__('Data retrieved from %d pages.', 'alchemer-reviews'), $pages_fetched);
            }
            
            // Add note if we got fewer than requested
            if ($found_responses_count < $max_reviews) {
                $message .= ' ' . sprintf(
                    __('Note: Requested %d reviews but only found %d matching your criteria.', 'alchemer-reviews'),
                    $max_reviews,
                    $found_responses_count
                );
            }
            
            // Add skip info if any were skipped
            if ($skipped_edited_count > 0 || $skipped_count > 0) {
                $message .= ' ' . sprintf(
                    __('Skipped %d responses (%d protected, %d invalid).', 'alchemer-reviews'),
                    $skipped_edited_count + $skipped_count,
                    $skipped_edited_count,
                    $skipped_count
                );
            }
            
            // Add error details in debug mode
            if (!empty($errors) && defined('WP_DEBUG') && WP_DEBUG) {
                $error_message = ' ' . sprintf(__('Error details: %s', 'alchemer-reviews'), 
                    implode('; ', array_slice($errors, 0, 3))
                );
                
                if (count($errors) > 3) {
                    $error_message .= '... ' . sprintf(__('and %d more', 'alchemer-reviews'), count($errors) - 3);
                }
                
                $message .= $error_message;
            }
            
            return array(
                'success' => true,
                'message' => $message,
                'imported_count' => $created_count + $updated_count,
                'created_count' => $created_count,
                'updated_count' => $updated_count,
                'skipped_edited_count' => $skipped_edited_count,
                'skipped_count' => $skipped_count,
                'target_rating' => $target_rating,
                'max_reviews' => $max_reviews,
                'pages_fetched' => $pages_fetched,
                'found_responses_count' => $found_responses_count
            );
        } else {
            // No reviews were imported
            if ($skipped_edited_count > 0) {
                return array(
                    'success' => true,
                    'message' => sprintf(__('No new reviews imported. %d response(s) were protected from import (Skip Overwrite was enabled).', 'alchemer-reviews'), $skipped_edited_count),
                    'imported_count' => 0,
                    'created_count' => 0,
                    'updated_count' => 0,
                    'skipped_edited_count' => $skipped_edited_count,
                    'skipped_count' => $skipped_count,
                );
            } else if ($skipped_count > 0) {
                return array(
                    'success' => false,
                    'message' => sprintf(__('No reviews were imported. %d response(s) were skipped due to missing data or invalid content.', 'alchemer-reviews'), $skipped_count),
                    'imported_count' => 0,
                    'created_count' => 0,
                    'updated_count' => 0,
                    'skipped_edited_count' => 0,
                    'skipped_count' => $skipped_count,
                );
            } else {
                return array(
                    'success' => false,
                    'message' => __('Failed to import reviews. Please check your field mappings and try again.', 'alchemer-reviews'),
                    'imported_count' => 0,
                    'created_count' => 0,
                    'updated_count' => 0,
                    'skipped_edited_count' => 0,
                    'skipped_count' => 0,
                );
            }
        }
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
     * Create a review post from a survey response
     *
     * @param array $response Survey response data
     * @return array Result array with success status and message or post ID
     */
    private function create_review_from_response($response) {
        // Get field mappings
        $mappings = $this->get_field_mappings();
        $rating_question_id = $mappings['rating_question'];
        $reviewer_name_field = $mappings['reviewer_name'];
        
        // First check if this response ID already exists
        $existing_review_id = 0;
        if (isset($response['id'])) {
            // Check if this review already exists by looking for the response ID in post meta
            $existing_reviews = get_posts(array(
                'post_type' => 'review',
                'meta_key' => '_alchemer_response_id',
                'meta_value' => $response['id'],
                'posts_per_page' => 1,
                'fields' => 'ids',
            ));
            
            if (!empty($existing_reviews)) {
                $existing_review_id = $existing_reviews[0];
                
                // Check if the review has been manually edited
                $manually_edited = get_post_meta($existing_review_id, '_alchemer_manually_edited', true);
                
                if ($manually_edited === '1') {
                    return array(
                        'success' => false,
                        'message' => sprintf(__('Skipping response ID %s: Review exists and has been manually edited', 'alchemer-reviews'), $response['id']),
                    );
                }
                
                // If not manually edited, we'll update it - continue with processing
            }
        }
        
        // For debugging - log the full response structure
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Alchemer API Response: ' . print_r($response, true));
        }
        
        // Check if the response data is in the expected format
        if (!isset($response['survey_data']) && is_array($response)) {
            // It might be directly in the response array
            $survey_data = $response;
        } else {
            $survey_data = isset($response['survey_data']) ? $response['survey_data'] : array();
        }
        
        // Check if the rating question exists in the survey data
        if (!isset($survey_data[$rating_question_id])) {
            return array(
                'success' => false,
                'message' => sprintf(__('Rating question (ID: %s) not found in survey response. Available question IDs: %s', 'alchemer-reviews'), 
                    $rating_question_id, 
                    implode(', ', array_keys($survey_data))
                ),
            );
        }
        
        // Get the rating question data
        $rating_question = $survey_data[$rating_question_id];
        
        // Extract the rating value
        $rating = 0;
        if (isset($rating_question['answer'])) {
            // Direct answer field
            $rating = intval($rating_question['answer']);
        } elseif (isset($rating_question['answer_id'])) {
            // Answer ID field
            $rating = intval($rating_question['answer_id']);
        }
        
        // Skip reviews with no rating
        if ($rating <= 0) {
            return array(
                'success' => false,
                'message' => sprintf(__('Skipping response ID %s: No rating value found', 'alchemer-reviews'), 
                    isset($response['id']) ? $response['id'] : 'unknown'
                ),
            );
        }
        
        // Extract the review content from the comments
        $content = '';
        if (isset($rating_question['comments'])) {
            $content = $rating_question['comments'];
        } elseif (isset($rating_question['comment'])) {
            $content = $rating_question['comment'];
        }
        
        // If still no content, look for it at the response level
        if (empty($content) && isset($response['comments'])) {
            $content = $response['comments'];
        } elseif (empty($content) && isset($response['comment'])) {
            $content = $response['comment'];
        }
        
        // Last attempt - check if there are other fields in the question that might contain the content
        if (empty($content) && is_array($rating_question)) {
            // Look for text fields that might contain review content
            foreach ($rating_question as $key => $value) {
                if (is_string($value) && strlen($value) > 10 && $key !== 'question') {
                    $content = $value;
                    break;
                }
            }
        }
        
        // Skip reviews with empty comments
        if (empty($content)) {
            return array(
                'success' => false,
                'message' => sprintf(__('Skipping response ID %s: No review content found', 'alchemer-reviews'), 
                    isset($response['id']) ? $response['id'] : 'unknown'
                ),
            );
        }
        
        // Get reviewer name if mapped
        $reviewer_name = __('Anonymous', 'alchemer-reviews');
        if (!empty($reviewer_name_field) && isset($survey_data[$reviewer_name_field])) {
            $reviewer_name_data = $survey_data[$reviewer_name_field];
            if (isset($reviewer_name_data['answer'])) {
                $reviewer_name = sanitize_text_field($reviewer_name_data['answer']);
            } elseif (is_string($reviewer_name_data)) {
                $reviewer_name = sanitize_text_field($reviewer_name_data);
            }
        }
        
        // Format the reviewer name to proper case (first letter uppercase, rest lowercase)
        $reviewer_name = $this->format_name_proper_case($reviewer_name);
        
        // Use reviewer name as the title
        $title = !empty($reviewer_name) ? $reviewer_name : __('Anonymous Review', 'alchemer-reviews');
        
        // Set post date from survey response date if available
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
        
        // Prepare post data
        $post_data = array(
            'post_title' => $title,
            'post_content' => wp_kses_post($content),
            'post_status' => 'publish',
            'post_type' => 'review',
            'post_date' => $post_date,
        );
        
        // Create or update the post
        if ($existing_review_id > 0) {
            // Update existing post
            $post_data['ID'] = $existing_review_id;
            $post_id = wp_update_post($post_data);
            $action = 'updated';
        } else {
            // Create new post
            $post_data['post_author'] = get_current_user_id();
            $post_id = wp_insert_post($post_data);
            $action = 'created';
        }
        
        if (is_wp_error($post_id)) {
            return array(
                'success' => false,
                'message' => $post_id->get_error_message(),
            );
        }
        
        // Save survey response ID as post meta
        if (isset($response['id'])) {
            update_post_meta($post_id, '_alchemer_response_id', $response['id']);
        }
        
        // Save reviewer name
        update_post_meta($post_id, '_alchemer_reviewer_name', $reviewer_name);
        
        // Save rating
        update_post_meta($post_id, '_alchemer_rating', $rating);
        
        // Save the raw response data in case we need it later
        update_post_meta($post_id, '_alchemer_response_data', $response);
        
        // Set manually_edited flag to 0 for newly imported or updated reviews
        update_post_meta($post_id, '_alchemer_manually_edited', '0');
        
        return array(
            'success' => true,
            'post_id' => $post_id,
            'action' => $action,
        );
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