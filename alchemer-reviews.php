<?php
/**
 * Plugin Name: Alchemer Reviews
 * Description: A plugin to import and manage Alchemer survey responses as reviews in WordPress.
 * Version: 1.0.17
 * Author: Braudy Pedrosa
 * Text Domain: alchemer-reviews
 * Domain Path: /languages
 * Update URI: https://github.com/braudypedrosa/alchemer-reviews
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

// Define plugin constants
define( 'ALCHEMER_REVIEWS_VERSION', '1.0.17' );
define( 'ALCHEMER_REVIEWS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'ALCHEMER_REVIEWS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'ALCHEMER_REVIEWS_PLUGIN_FILE', __FILE__ );
define( 'ALCHEMER_REVIEWS_GITHUB_REPO', 'braudypedrosa/alchemer-reviews' );
define( 'ALCHEMER_REVIEWS_GITHUB_ASSET_NAME', 'alchemer-reviews.zip' );

// Include the file that registers the custom post type
require_once ALCHEMER_REVIEWS_PLUGIN_DIR . 'includes/class-alchemer-reviews-post-types.php';

// Include the file that handles settings
require_once ALCHEMER_REVIEWS_PLUGIN_DIR . 'includes/class-alchemer-reviews-settings.php';

// Include the file that handles API communication
require_once ALCHEMER_REVIEWS_PLUGIN_DIR . 'includes/class-alchemer-reviews-api.php';

// Include the file that handles importing reviews
require_once ALCHEMER_REVIEWS_PLUGIN_DIR . 'includes/class-alchemer-reviews-importer.php';

// Include the file that handles GitHub release updates
require_once ALCHEMER_REVIEWS_PLUGIN_DIR . 'includes/class-alchemer-reviews-github-updater.php';

// Include dependencies
require_once ALCHEMER_REVIEWS_PLUGIN_DIR . 'includes/reviews-carousel/alchemer-review-carousel.php';

// Register admin menu
require_once ALCHEMER_REVIEWS_PLUGIN_DIR . 'includes/reviews-carousel/alchemer-review-carousel-docs.php';

// Hook to initialize the plugin
add_action( 'plugins_loaded', 'alchemer_reviews_init' );

/**
 * Initialize the plugin functionalities
 * 
 * @return void
 */
function alchemer_reviews_init() {
    // Initialize custom post types
    $post_types = new Alchemer_Reviews_Post_Types();
    $post_types->init();
    
    // Initialize settings
    $settings = new Alchemer_Reviews_Settings();
    $settings->init();
    
    // Initialize importer
    $importer = new Alchemer_Reviews_Importer();
    $importer->init();

    // Initialize GitHub release updater
    $github_updater = new Alchemer_Reviews_GitHub_Updater( ALCHEMER_REVIEWS_PLUGIN_FILE );
    $github_updater->init();

    alchemer_reviews_maybe_schedule_daily_import();
}

// Register activation hook
register_activation_hook( __FILE__, 'alchemer_reviews_activate' );

/**
 * Plugin activation callback
 * 
 * @return void
 */
function alchemer_reviews_activate() {
    // Trigger post type registration
    $post_types = new Alchemer_Reviews_Post_Types();
    $post_types->register_review_post_type();

    alchemer_reviews_maybe_schedule_daily_import();
    
    // Clear the permalinks
    flush_rewrite_rules();
}

/**
 * Schedule the daily importer if the saved setting is enabled.
 *
 * @return void
 */
function alchemer_reviews_maybe_schedule_daily_import() {
    $mappings = get_option( 'alchemer_reviews_field_mappings', array() );

    if ( empty( $mappings['auto_import'] ) ) {
        return;
    }

    if ( ! wp_next_scheduled( 'alchemer_reviews_daily_import' ) ) {
        wp_schedule_event( time() + MINUTE_IN_SECONDS, 'daily', 'alchemer_reviews_daily_import' );
    }
}

// Register deactivation hook
register_deactivation_hook( __FILE__, 'alchemer_reviews_deactivate' );

/**
 * Plugin deactivation callback
 * 
 * @return void
 */
function alchemer_reviews_deactivate() {
    wp_clear_scheduled_hook('alchemer_reviews_daily_import');

    // Clear the permalinks
    flush_rewrite_rules();
}
