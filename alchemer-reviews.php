<?php
/**
 * Plugin Name: Alchemer Reviews
 * Description: A plugin to manage and display reviews.
 * Version: 1.0.0
 * Author: Your Name
 * Text Domain: alchemer-reviews
 * Domain Path: /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

// Define plugin constants
define( 'ALCHEMER_REVIEWS_VERSION', '1.0.0' );
define( 'ALCHEMER_REVIEWS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'ALCHEMER_REVIEWS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Include the file that registers the custom post type
require_once ALCHEMER_REVIEWS_PLUGIN_DIR . 'includes/class-alchemer-reviews-post-types.php';

// Include the file that handles settings
require_once ALCHEMER_REVIEWS_PLUGIN_DIR . 'includes/class-alchemer-reviews-settings.php';

// Include the file that handles API communication
require_once ALCHEMER_REVIEWS_PLUGIN_DIR . 'includes/class-alchemer-reviews-api.php';

// Include the file that handles importing reviews
require_once ALCHEMER_REVIEWS_PLUGIN_DIR . 'includes/class-alchemer-reviews-importer.php';

// Include the file that handles displaying reviews
require_once ALCHEMER_REVIEWS_PLUGIN_DIR . 'includes/class-alchemer-reviews-display.php';

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
    
    // Initialize display
    $display = new Alchemer_Reviews_Display();
    $display->init();
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
    
    // Clear the permalinks
    flush_rewrite_rules();
}

// Register deactivation hook
register_deactivation_hook( __FILE__, 'alchemer_reviews_deactivate' );

/**
 * Plugin deactivation callback
 * 
 * @return void
 */
function alchemer_reviews_deactivate() {
    // Clear the permalinks
    flush_rewrite_rules();
} 