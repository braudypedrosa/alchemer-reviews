<?php
/**
 * Class for registering custom post types for the Alchemer Reviews plugin
 *
 * @since 1.0.0
 */
class Alchemer_Reviews_Post_Types {

    /**
     * Initialize the class and set its hooks.
     *
     * @return void
     */
    public function init() {
        // Register custom post types
        add_action( 'init', array( $this, 'register_review_post_type' ) );
        
        // Add meta boxes
        add_action( 'add_meta_boxes', array( $this, 'add_review_meta_boxes' ) );
        
        // Save meta box data
        add_action( 'save_post_review', array( $this, 'save_review_meta' ) );
        
        // Automatically set manually_edited flag when content is updated through the editor
        add_action( 'post_updated', array( $this, 'maybe_set_manually_edited_flag' ), 10, 3 );
        
        // Add columns to the reviews list
        add_filter( 'manage_review_posts_columns', array( $this, 'add_review_columns' ) );
        
        // Display column content
        add_action( 'manage_review_posts_custom_column', array( $this, 'display_review_column_content' ), 10, 2 );
        
        // Make columns sortable
        add_filter( 'manage_edit-review_sortable_columns', array( $this, 'make_review_columns_sortable' ) );
        
        // Sort by custom columns
        add_action( 'pre_get_posts', array( $this, 'sort_reviews_by_custom_column' ) );
        
        // Enqueue admin scripts and styles
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
        
        // AJAX handler for toggle skip overwrite
        add_action( 'wp_ajax_toggle_skip_overwrite', array( $this, 'ajax_toggle_skip_overwrite' ) );
        
        // AJAX handler to approve AI review
        add_action( 'wp_ajax_approve_ai_review', array( $this, 'ajax_approve_ai_review' ) );
        
        // AJAX handler to generate an AI suggestion for a review
        add_action( 'wp_ajax_generate_ai_suggestion', array( $this, 'ajax_generate_ai_suggestion' ) );
    }

    /**
     * Register the 'review' custom post type
     *
     * @return void
     */
    public function register_review_post_type() {
        $labels = array(
            'name'                  => _x( 'Reviews', 'Post type general name', 'alchemer-reviews' ),
            'singular_name'         => _x( 'Review', 'Post type singular name', 'alchemer-reviews' ),
            'menu_name'             => _x( 'Reviews', 'Admin Menu text', 'alchemer-reviews' ),
            'name_admin_bar'        => _x( 'Review', 'Add New on Toolbar', 'alchemer-reviews' ),
            'add_new'               => __( 'Add New', 'alchemer-reviews' ),
            'add_new_item'          => __( 'Add New Review', 'alchemer-reviews' ),
            'new_item'              => __( 'New Review', 'alchemer-reviews' ),
            'edit_item'             => __( 'Edit Review', 'alchemer-reviews' ),
            'view_item'             => __( 'View Review', 'alchemer-reviews' ),
            'all_items'             => __( 'All Reviews', 'alchemer-reviews' ),
            'search_items'          => __( 'Search Reviews', 'alchemer-reviews' ),
            'parent_item_colon'     => __( 'Parent Reviews:', 'alchemer-reviews' ),
            'not_found'             => __( 'No reviews found.', 'alchemer-reviews' ),
            'not_found_in_trash'    => __( 'No reviews found in Trash.', 'alchemer-reviews' ),
            'featured_image'        => _x( 'Review Cover Image', 'Overrides the "Featured Image" phrase', 'alchemer-reviews' ),
            'set_featured_image'    => _x( 'Set cover image', 'Overrides the "Set featured image" phrase', 'alchemer-reviews' ),
            'remove_featured_image' => _x( 'Remove cover image', 'Overrides the "Remove featured image" phrase', 'alchemer-reviews' ),
            'use_featured_image'    => _x( 'Use as cover image', 'Overrides the "Use as featured image" phrase', 'alchemer-reviews' ),
            'archives'              => _x( 'Review archives', 'The post type archive label used in nav menus', 'alchemer-reviews' ),
            'insert_into_item'      => _x( 'Insert into review', 'Overrides the "Insert into post" phrase', 'alchemer-reviews' ),
            'uploaded_to_this_item' => _x( 'Uploaded to this review', 'Overrides the "Uploaded to this post" phrase', 'alchemer-reviews' ),
            'filter_items_list'     => _x( 'Filter reviews list', 'Screen reader text for the filter links', 'alchemer-reviews' ),
            'items_list_navigation' => _x( 'Reviews list navigation', 'Screen reader text for the pagination', 'alchemer-reviews' ),
            'items_list'            => _x( 'Reviews list', 'Screen reader text for the items list', 'alchemer-reviews' ),
        );

        $args = array(
            'labels'             => $labels,
            'public'             => true,
            'publicly_queryable' => true,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'query_var'          => true,
            'rewrite'            => array( 'slug' => 'review' ),
            'capability_type'    => 'post',
            'has_archive'        => true,
            'hierarchical'       => false,
            'menu_position'      => null,
            'menu_icon'          => 'dashicons-star-filled',
            'supports'           => array( 'title', 'editor', 'author', 'thumbnail', 'excerpt' ),
            'show_in_rest'       => true,
        );

        register_post_type( 'review', $args );
    }

    /**
     * Add meta boxes to the review edit screen
     *
     * @return void
     */
    public function add_review_meta_boxes() {
        add_meta_box(
            'alchemer_review_details',
            __( 'Review Details', 'alchemer-reviews' ),
            array( $this, 'render_review_details_meta_box' ),
            'review',
            'side',
            'high'
        );
        add_meta_box(
            'alchemer_ai_suggestion',
            __( 'AI Suggestion', 'alchemer-reviews' ),
            array( $this, 'render_ai_suggestion_meta_box' ),
            'review',
            'normal',
            'high'
        );
        add_meta_box(
            'alchemer_original_review',
            __( 'Original Review Content', 'alchemer-reviews' ),
            array( $this, 'render_original_review_meta_box' ),
            'review',
            'normal',
            'high'
        );
    }

    /**
     * Render the review details meta box
     *
     * @param WP_Post $post The post object.
     * @return void
     */
    public function render_review_details_meta_box( $post ) {
        // Add nonce for security
        wp_nonce_field( 'alchemer_review_details', 'alchemer_review_details_nonce' );
        
        // Get meta values
        $reviewer_name = get_post_meta( $post->ID, '_alchemer_reviewer_name', true );
        $rating = get_post_meta( $post->ID, '_alchemer_rating', true );
        $skip_overwrite = get_post_meta( $post->ID, '_alchemer_manually_edited', true );
        
        // Get response ID
        $response_id = get_post_meta( $post->ID, '_alchemer_response_id', true );
        
        // Output fields
        ?>
        <p>
            <label for="alchemer_reviewer_name"><?php _e( 'Reviewer Name:', 'alchemer-reviews' ); ?></label>
            <input type="text" id="alchemer_reviewer_name" name="alchemer_reviewer_name" value="<?php echo esc_attr( $reviewer_name ); ?>" class="widefat">
        </p>
        
        <p>
            <label for="alchemer_rating"><?php _e( 'Rating:', 'alchemer-reviews' ); ?></label>
            <select id="alchemer_rating" name="alchemer_rating" class="widefat">
                <option value="0" <?php selected( $rating, 0 ); ?>><?php _e( 'No rating', 'alchemer-reviews' ); ?></option>
                <option value="1" <?php selected( $rating, 1 ); ?>>1 - <?php _e( 'Poor', 'alchemer-reviews' ); ?></option>
                <option value="2" <?php selected( $rating, 2 ); ?>>2 - <?php _e( 'Fair', 'alchemer-reviews' ); ?></option>
                <option value="3" <?php selected( $rating, 3 ); ?>>3 - <?php _e( 'Average', 'alchemer-reviews' ); ?></option>
                <option value="4" <?php selected( $rating, 4 ); ?>>4 - <?php _e( 'Good', 'alchemer-reviews' ); ?></option>
                <option value="5" <?php selected( $rating, 5 ); ?>>5 - <?php _e( 'Excellent', 'alchemer-reviews' ); ?></option>
            </select>
        </p>
        
        <div class="rating-display">
            <?php echo $this->get_rating_stars( $rating ); ?>
        </div>
        
        <p>
            <label>
                <input type="checkbox" name="alchemer_manually_edited" value="1" <?php checked( $skip_overwrite, '1' ); ?>>
                <?php _e( 'Skip Overwrite (protect from updates during imports)', 'alchemer-reviews' ); ?>
            </label>
        </p>
        
        <?php if ( $response_id ) : ?>
            <p>
                <label><?php _e( 'Alchemer Response ID:', 'alchemer-reviews' ); ?></label>
                <span><?php echo esc_html( $response_id ); ?></span>
            </p>
        <?php endif; ?>
        <?php
    }

    /**
     * Render the AI suggestion meta box
     *
     * @param WP_Post $post The post object.
     * @return void
     */
    public function render_ai_suggestion_meta_box( $post ) {
        wp_nonce_field( 'alchemer_ai_suggestion', 'alchemer_ai_suggestion_nonce' );
        $ai_suggestion = get_post_meta( $post->ID, 'ai_suggestion', true );
        $pending = get_post_meta( $post->ID, 'pending_ai_approval', true );
        if ( $pending === '1' && !empty($ai_suggestion) ) {
            echo '<textarea id="ai_suggestion" name="ai_suggestion" class="widefat" rows="5">' . esc_textarea($ai_suggestion) . '</textarea>';
            echo '<p><button type="button" class="button button-primary" id="approve-ai-suggestion">' . __( 'Approve AI Suggestion', 'alchemer-reviews' ) . '</button></p>';
        } else {
            echo '<p>' . __( 'No AI suggestion available or already approved.', 'alchemer-reviews' ) . '</p>';
        }
    }

    /**
     * Render the original review content meta box
     *
     * @param WP_Post $post The post object.
     * @return void
     */
    public function render_original_review_meta_box( $post ) {
        wp_nonce_field( 'alchemer_original_review', 'alchemer_original_review_nonce' );
        $original_content = $post->post_content;
        echo '<div class="original-review-content">' . wp_kses_post( $original_content ) . '</div>';
    }

    /**
     * Save review meta data
     *
     * @param int $post_id The post ID.
     * @return void
     */
    public function save_review_meta( $post_id ) {
        // Check if nonce is set
        if ( ! isset( $_POST['alchemer_review_details_nonce'] ) ) {
            return;
        }
        
        // Verify nonce
        if ( ! wp_verify_nonce( $_POST['alchemer_review_details_nonce'], 'alchemer_review_details' ) ) {
            return;
        }
        
        // If auto-saving, do nothing
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }
        
        // Check permissions
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }
        
        // Save reviewer name
        if ( isset( $_POST['alchemer_reviewer_name'] ) ) {
            update_post_meta( $post_id, '_alchemer_reviewer_name', sanitize_text_field( $_POST['alchemer_reviewer_name'] ) );
        }
        
        // Save rating
        if ( isset( $_POST['alchemer_rating'] ) ) {
            update_post_meta( $post_id, '_alchemer_rating', intval( $_POST['alchemer_rating'] ) );
        }
        
        // Save manually edited flag
        $skip_overwrite = isset( $_POST['alchemer_manually_edited'] ) ? '1' : '0';
        update_post_meta( $post_id, '_alchemer_manually_edited', $skip_overwrite );
    }

    /**
     * Automatically set manually_edited flag when content is updated through the editor
     *
     * @param int     $post_id     The post ID.
     * @param WP_Post $post_after  The post object after the update.
     * @param WP_Post $post_before The post object before the update.
     * @return void
     */
    public function maybe_set_manually_edited_flag( $post_id, $post_after, $post_before ) {
        // Check if the post type is 'review'
        if ( $post_after->post_type !== 'review' ) {
            return;
        }
        
        // Check if the content has been updated
        if ( $post_after->post_content === $post_before->post_content ) {
            return;
        }
        
        // Set manually_edited flag to 1
        update_post_meta( $post_id, '_alchemer_manually_edited', '1' );
    }

    /**
     * Add custom columns to the reviews list
     *
     * @param array $columns The default columns.
     * @return array Modified columns.
     */
    public function add_review_columns( $columns ) {
        $new_columns = array();
        
        // Insert columns after title
        foreach ( $columns as $key => $value ) {
            $new_columns[ $key ] = $value;
            
            if ( $key === 'title' ) {
                $new_columns['review_content'] = __( 'Review Content', 'alchemer-reviews' );
                $new_columns['rating'] = __( 'Rating', 'alchemer-reviews' );
                $new_columns['skip_overwrite'] = __( 'Skip Overwrite', 'alchemer-reviews' );
                $new_columns['actions'] = __( 'Actions', 'alchemer-reviews' );
            }
        }
        
        return $new_columns;
    }

    /**
     * Display content for custom columns
     *
     * @param string $column_name The column name.
     * @param int    $post_id     The post ID.
     * @return void
     */
    public function display_review_column_content( $column_name, $post_id ) {
        switch ( $column_name ) {
            case 'review_content':
                $post = get_post($post_id);
                $excerpt = wp_trim_words($post->post_content, 20, '...');
                echo esc_html($excerpt);
                break;
                
            case 'rating':
                $rating = get_post_meta( $post_id, '_alchemer_rating', true );
                echo $this->get_rating_stars( $rating );
                break;
                
            case 'skip_overwrite':
                $skip_overwrite = get_post_meta( $post_id, '_alchemer_manually_edited', true );
                $current_status = $skip_overwrite === '1' ? 'on' : 'off';
                $nonce = wp_create_nonce( 'alchemer_toggle_skip_overwrite_' . $post_id );
                
                echo '<div class="alchemer-toggle-container">';
                echo '<label class="alchemer-toggle-switch">';
                echo '<input type="checkbox" class="alchemer-toggle-skip-overwrite" ' . checked( $current_status, 'on', false ) . ' ';
                echo 'data-post-id="' . esc_attr( $post_id ) . '" ';
                echo 'data-nonce="' . esc_attr( $nonce ) . '" ';
                echo 'data-status="' . esc_attr( $current_status ) . '">';
                echo '<span class="alchemer-toggle-slider"></span>';
                echo '</label>';
                echo '<span class="alchemer-toggle-status screen-reader-text">' . 
                     ($current_status === 'on' ? __('Protected from import overwriting', 'alchemer-reviews') : 
                                               __('Can be overwritten during import', 'alchemer-reviews')) . 
                     '</span>';
                echo '</div>';
                break;
                
            case 'actions':
                echo '<span class="generate-ai-suggestion" data-post-id="' . esc_attr($post_id) . '" title="Generate AI suggestion" style="cursor:pointer;display:inline-block;vertical-align:middle;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 4V2"/><path d="M15 16v-2"/><path d="M8 9h2"/><path d="M20 9h2"/><path d="M17.8 6.2l1.4-1.4"/><path d="M17.8 11.8l1.4 1.4"/><path d="M2 15l6 6"/><path d="M8 21l-6-6"/><path d="M18 9a6 6 0 1 1-6-6"/></svg>
                </span>';
                break;
        }
    }

    /**
     * Make custom columns sortable
     *
     * @param array $columns The sortable columns.
     * @return array Modified sortable columns.
     */
    public function make_review_columns_sortable( $columns ) {
        $columns['rating'] = 'rating';
        $columns['review_content'] = 'review_content';
        $columns['skip_overwrite'] = 'manually_edited';
        
        return $columns;
    }

    /**
     * Sort reviews by custom column
     *
     * @param WP_Query $query The query object.
     * @return void
     */
    public function sort_reviews_by_custom_column( $query ) {
        if ( ! is_admin() || ! $query->is_main_query() || $query->get( 'post_type' ) !== 'review' ) {
            return;
        }
        
        $orderby = $query->get( 'orderby' );
        
        switch ( $orderby ) {
            case 'review_content':
                $query->set( 'orderby', 'content' );
                break;
                
            case 'rating':
                $query->set( 'meta_key', '_alchemer_rating' );
                $query->set( 'orderby', 'meta_value_num' );
                break;
                
            case 'manually_edited':
                $query->set( 'meta_key', '_alchemer_manually_edited' );
                $query->set( 'orderby', 'meta_value' );
                break;
        }
    }

    /**
     * Get HTML for rating stars
     *
     * @param int $rating The rating value (0-5).
     * @return string HTML for rating stars.
     */
    private function get_rating_stars( $rating ) {
        $rating = intval( $rating );
        
        if ( $rating <= 0 ) {
            return '<span class="rating-text">' . __( 'No rating', 'alchemer-reviews' ) . '</span>';
        }
        
        $stars = '';
        $max_stars = 5;
        
        // Add filled stars
        for ( $i = 1; $i <= $rating; $i++ ) {
            $stars .= '<span class="dashicons dashicons-star-filled" style="color: #ffb900;"></span>';
        }
        
        // Add empty stars
        for ( $i = $rating + 1; $i <= $max_stars; $i++ ) {
            $stars .= '<span class="dashicons dashicons-star-empty" style="color: #ccc;"></span>';
        }
        
        // Add numeric rating
        $stars .= ' <span class="rating-text">(' . $rating . ' / ' . $max_stars . ')</span>';
        
        return $stars;
    }

    /**
     * Enqueue admin scripts and styles
     *
     * @param string $hook The current admin page.
     * @return void
     */
    public function enqueue_admin_scripts( $hook ) {
        // Only load on the reviews list page
        if ( 'edit.php' !== $hook || 'review' !== get_current_screen()->post_type ) {
            return;
        }
        
        // Register and enqueue CSS
        wp_register_style(
            'alchemer-reviews-admin',
            ALCHEMER_REVIEWS_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            ALCHEMER_REVIEWS_VERSION
        );
        wp_enqueue_style( 'alchemer-reviews-admin' );

        // Enqueue Tailwind and plugin button styles for the reviews list page
        $tailwind_url = 'https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css';
        $admin_tailwind_url = ALCHEMER_REVIEWS_PLUGIN_URL . 'assets/css/admin-tailwind.css';
        $admin_tailwind_override_url = ALCHEMER_REVIEWS_PLUGIN_URL . 'assets/css/admin-tailwind-override.css';
        if ( defined('WP_DEBUG') && WP_DEBUG ) {
            error_log('Enqueuing Tailwind: ' . $tailwind_url);
            error_log('Enqueuing admin-tailwind: ' . $admin_tailwind_url);
            error_log('Enqueuing admin-tailwind-override: ' . $admin_tailwind_override_url);
        }
        wp_enqueue_style(
            'tailwind-alchemer',
            $tailwind_url,
            array(),
            '2.2.19'
        );
        wp_enqueue_style(
            'alchemer-tailwind-admin',
            $admin_tailwind_url,
            array('tailwind-alchemer'),
            ALCHEMER_REVIEWS_VERSION
        );
        wp_enqueue_style(
            'alchemer-tailwind-override',
            $admin_tailwind_override_url,
            array('tailwind-alchemer', 'alchemer-tailwind-admin'),
            ALCHEMER_REVIEWS_VERSION . '.' . time()
        );

        // Register and enqueue JavaScript
        wp_register_script(
            'alchemer-reviews-admin',
            ALCHEMER_REVIEWS_PLUGIN_URL . 'assets/js/admin.js',
            array( 'jquery' ),
            ALCHEMER_REVIEWS_VERSION,
            true
        );
        wp_enqueue_script( 'alchemer-reviews-admin' );
        
        // Localize script with data for AJAX
        wp_localize_script(
            'alchemer-reviews-admin',
            'alchemerReviewsAdmin',
            array(
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'toggleSkipText' => __( 'Toggling skip overwrite status...', 'alchemer-reviews' ),
                'errorText' => __( 'Error: ', 'alchemer-reviews' ),
                'protectedText' => __( 'Protected from import overwriting', 'alchemer-reviews' ),
                'unprotectedText' => __( 'Can be overwritten during import', 'alchemer-reviews' ),
                'nonce' => wp_create_nonce( 'test_alchemer_api_connection' ),
            )
        );

        // Add inline style for .alchemer-button for debugging
        add_action('admin_footer', function() {
            echo '<style>.alchemer-button { background: #2563eb !important; color: #fff !important; border-radius: 0.5rem !important; border: none !important; padding: 0.5rem 1.5rem !important; font-weight: 600 !important; font-size: 1rem !important; margin: 0 0.25rem; box-shadow: 0 1px 2px rgba(0,0,0,0.04); transition: background 0.2s; } .alchemer-button-primary { background: #2563eb !important; color: #fff !important; } .alchemer-button-secondary { background: #e5e7eb !important; color: #374151 !important; } .alchemer-button:hover { background: #1d4ed8 !important; }</style>';
        });
    }
    
    /**
     * AJAX handler for toggling skip overwrite status
     *
     * @return void
     */
    public function ajax_toggle_skip_overwrite() {
        // Check if we have the required data
        if ( ! isset( $_POST['post_id'] ) || ! isset( $_POST['nonce'] ) || ! isset( $_POST['status'] ) ) {
            wp_send_json_error( array(
                'message' => __( 'Missing required data', 'alchemer-reviews' ),
            ) );
        }
        
        // Get and sanitize data
        $post_id = intval( $_POST['post_id'] );
        $nonce = sanitize_text_field( $_POST['nonce'] );
        $current_status = sanitize_text_field( $_POST['status'] );
        
        // Verify nonce
        if ( ! wp_verify_nonce( $nonce, 'alchemer_toggle_skip_overwrite_' . $post_id ) ) {
            wp_send_json_error( array(
                'message' => __( 'Security check failed', 'alchemer-reviews' ),
            ) );
        }
        
        // Verify user capabilities
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            wp_send_json_error( array(
                'message' => __( 'You do not have permission to edit this review', 'alchemer-reviews' ),
            ) );
        }
        
        // Get the post to verify it exists and is a review
        $post = get_post( $post_id );
        if ( ! $post || 'review' !== $post->post_type ) {
            wp_send_json_error( array(
                'message' => __( 'Invalid review', 'alchemer-reviews' ),
            ) );
        }
        
        // Toggle the status
        $new_status = $current_status === 'on' ? '0' : '1';
        $result = update_post_meta( $post_id, '_alchemer_manually_edited', $new_status );
        
        if ( $result ) {
            wp_send_json_success( array(
                'message' => __( 'Status updated successfully', 'alchemer-reviews' ),
                'new_status' => $new_status === '1' ? 'on' : 'off',
                'post_id' => $post_id,
            ) );
        } else {
            wp_send_json_error( array(
                'message' => __( 'Failed to update status', 'alchemer-reviews' ),
            ) );
        }
    }

    /**
     * AJAX handler to approve AI review: overwrite post_content with ai_suggestion, set pending_ai_approval=0, and publish the post
     *
     * @return void
     */
    public function ajax_approve_ai_review() {
        if ( ! isset( $_POST['post_id'] ) || ! isset( $_POST['nonce'] ) ) {
            wp_send_json_error( array( 'message' => __( 'Missing required data', 'alchemer-reviews' ) ) );
        }
        $post_id = intval( $_POST['post_id'] );
        $nonce = sanitize_text_field( $_POST['nonce'] );
        // Debug logging for nonce and user
        if ( defined('WP_DEBUG') && WP_DEBUG ) {
            error_log('AJAX approve_ai_review: Nonce received: ' . $nonce);
            error_log('AJAX approve_ai_review: Fresh nonce: ' . wp_create_nonce('test_alchemer_api_connection'));
            error_log('AJAX approve_ai_review: Current user ID: ' . get_current_user_id());
        }
        if ( ! wp_verify_nonce( $nonce, 'test_alchemer_api_connection' ) ) {
            wp_send_json_error( array( 'message' => __( 'Security check failed', 'alchemer-reviews' ) ) );
        }
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            wp_send_json_error( array( 'message' => __( 'You do not have permission to approve this review', 'alchemer-reviews' ) ) );
        }
        $ai_suggestion = isset( $_POST['ai_suggestion'] ) ? wp_kses_post( $_POST['ai_suggestion'] ) : get_post_meta( $post_id, 'ai_suggestion', true );
        if ( empty( $ai_suggestion ) ) {
            wp_send_json_error( array( 'message' => __( 'No AI suggestion found for this review.', 'alchemer-reviews' ) ) );
        }
        // Overwrite post_content and publish
        $update = wp_update_post( array(
            'ID' => $post_id,
            'post_content' => $ai_suggestion,
            'post_status' => 'publish',
        ), true );
        if ( is_wp_error( $update ) ) {
            wp_send_json_error( array( 'message' => $update->get_error_message() ) );
        }
        update_post_meta( $post_id, 'pending_ai_approval', '0' );
        wp_send_json_success( array( 'message' => __( 'Review approved and published.', 'alchemer-reviews' ) ) );
    }

    /**
     * AJAX handler to generate an AI suggestion for a review
     *
     * @return void
     */
    public function ajax_generate_ai_suggestion() {
        // Check permissions and nonce
        if ( ! isset( $_POST['post_id'] ) || ! isset( $_POST['nonce'] ) ) {
            wp_send_json_error( array( 'message' => __( 'Missing required data', 'alchemer-reviews' ) ) );
        }
        $post_id = intval( $_POST['post_id'] );
        $nonce = sanitize_text_field( $_POST['nonce'] );
        // Debug logging for nonce and user
        if ( defined('WP_DEBUG') && WP_DEBUG ) {
            error_log('AJAX generate_ai_suggestion: Nonce received: ' . $nonce);
            error_log('AJAX generate_ai_suggestion: Fresh nonce: ' . wp_create_nonce('test_alchemer_api_connection'));
            error_log('AJAX generate_ai_suggestion: Current user ID: ' . get_current_user_id());
        }
        if ( ! wp_verify_nonce( $nonce, 'test_alchemer_api_connection' ) ) {
            wp_send_json_error( array( 'message' => __( 'Security check failed', 'alchemer-reviews' ) ) );
        }
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            wp_send_json_error( array( 'message' => __( 'You do not have permission to generate an AI suggestion for this review', 'alchemer-reviews' ) ) );
        }
        $post = get_post( $post_id );
        if ( ! $post || 'review' !== $post->post_type ) {
            wp_send_json_error( array( 'message' => __( 'Invalid review', 'alchemer-reviews' ) ) );
        }
        $content = $post->post_content;
        if ( empty( $content ) ) {
            wp_send_json_error( array( 'message' => __( 'No review content found.', 'alchemer-reviews' ) ) );
        }
        // Load the importer class and call Gemini
        if ( ! class_exists( 'Alchemer_Reviews_Importer' ) ) {
            require_once ALCHEMER_REVIEWS_PLUGIN_DIR . 'includes/class-alchemer-reviews-importer.php';
        }
        $importer = new Alchemer_Reviews_Importer();
        $ai = $importer->get_gemini_sentiment_and_suggestion( $content );
        if ( empty( $ai['suggestion'] ) ) {
            wp_send_json_error( array( 'message' => __( 'AI did not return a suggestion.', 'alchemer-reviews' ) ) );
        }
        wp_send_json_success( array( 'suggestion' => $ai['suggestion'], 'sentiment' => $ai['sentiment'] ) );
    }
} 