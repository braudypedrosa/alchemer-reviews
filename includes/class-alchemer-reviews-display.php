<?php
/**
 * Class for displaying reviews on the frontend
 *
 * @since 1.0.0
 */
class Alchemer_Reviews_Display {

    /**
     * Initialize the class and set its hooks
     *
     * @return void
     */
    public function init() {
        // Register shortcode
        add_shortcode( 'alchemer_reviews', array( $this, 'reviews_shortcode' ) );
        
        // Enqueue frontend scripts and styles
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
    }

    /**
     * Enqueue frontend scripts and styles
     *
     * @return void
     */
    public function enqueue_scripts() {
        // Register and enqueue stylesheet
        wp_register_style(
            'alchemer-reviews-style',
            ALCHEMER_REVIEWS_PLUGIN_URL . 'assets/css/frontend.css',
            array(),
            ALCHEMER_REVIEWS_VERSION
        );
        
        // Only enqueue when shortcode is used
        global $post;
        if ( is_a( $post, 'WP_Post' ) && has_shortcode( $post->post_content, 'alchemer_reviews' ) ) {
            wp_enqueue_style( 'alchemer-reviews-style' );
            wp_enqueue_style( 'dashicons' );
        }
    }

    /**
     * Reviews shortcode callback
     *
     * @param array $atts Shortcode attributes.
     * @return string Shortcode output.
     */
    public function reviews_shortcode( $atts ) {
        $atts = shortcode_atts( array(
            'count' => 5,
            'orderby' => 'date',
            'order' => 'DESC',
            'layout' => 'grid',
            'rating' => 0,
            'show_pagination' => 'yes',
            'show_rating' => 'yes',
            'show_date' => 'yes',
        ), $atts, 'alchemer_reviews' );
        
        // Validate attributes
        $count = absint( $atts['count'] );
        $orderby = in_array( $atts['orderby'], array( 'date', 'title', 'rating', 'rand' ) ) ? $atts['orderby'] : 'date';
        $order = in_array( strtoupper( $atts['order'] ), array( 'ASC', 'DESC' ) ) ? strtoupper( $atts['order'] ) : 'DESC';
        $layout = in_array( $atts['layout'], array( 'grid', 'list', 'slider' ) ) ? $atts['layout'] : 'grid';
        $rating = intval( $atts['rating'] );
        $show_pagination = $atts['show_pagination'] === 'yes';
        $show_rating = $atts['show_rating'] === 'yes';
        $show_date = $atts['show_date'] === 'yes';
        
        // Get current page
        $paged = get_query_var( 'paged' ) ? get_query_var( 'paged' ) : 1;
        
        // Set up the query arguments
        $args = array(
            'post_type' => 'review',
            'posts_per_page' => $count,
            'paged' => $paged,
            'orderby' => $orderby === 'rating' ? 'meta_value_num' : $orderby,
            'order' => $order,
        );
        
        // Add meta query for rating filter
        if ( $rating > 0 ) {
            $args['meta_query'] = array(
                array(
                    'key' => '_alchemer_rating',
                    'value' => $rating,
                    'compare' => '>=',
                    'type' => 'NUMERIC',
                ),
            );
        }
        
        // Set meta key for orderby rating
        if ( $orderby === 'rating' ) {
            $args['meta_key'] = '_alchemer_rating';
        }
        
        // Get the reviews
        $reviews_query = new WP_Query( $args );
        
        // Start output buffer
        ob_start();
        
        // Check if we have reviews
        if ( $reviews_query->have_posts() ) {
            // Container class based on layout
            $container_class = 'alchemer-reviews-container layout-' . esc_attr( $layout );
            
            // Start container
            echo '<div class="' . esc_attr( $container_class ) . '">';
            
            // Loop through reviews
            while ( $reviews_query->have_posts() ) {
                $reviews_query->the_post();
                
                // Get meta data
                $rating = get_post_meta( get_the_ID(), '_alchemer_rating', true );
                
                // Start review item
                echo '<div class="alchemer-review-item">';
                
                // Title (reviewer name)
                echo '<h3 class="alchemer-review-title">' . get_the_title() . '</h3>';
                
                // Meta information
                echo '<div class="alchemer-review-meta">';
                
                // Rating
                if ( $show_rating && ! empty( $rating ) ) {
                    echo '<div class="alchemer-review-rating">';
                    echo $this->get_rating_stars( $rating );
                    echo '</div>';
                }
                
                // Date
                if ( $show_date ) {
                    echo '<div class="alchemer-review-date">';
                    $review_date = get_post_meta( get_the_ID(), '_alchemer_review_date', true );
                    if ( ! empty( $review_date ) ) {
                        echo '<time datetime="' . esc_attr( get_the_date( 'c' ) ) . '">' . esc_html( $review_date ) . '</time>';
                    } else {
                        echo '<time datetime="' . esc_attr( get_the_date( 'c' ) ) . '">' . esc_html( get_the_date() ) . '</time>';
                    }
                    echo '</div>';
                }
                
                echo '</div>'; // End meta
                
                // Content
                echo '<div class="alchemer-review-content">' . apply_filters( 'the_content', get_the_content() ) . '</div>';
                
                echo '</div>'; // End review item
            }
            
            echo '</div>'; // End container
            
            // Pagination
            if ( $show_pagination && $reviews_query->max_num_pages > 1 ) {
                echo '<div class="alchemer-reviews-pagination">';
                $big = 999999999; // need an unlikely integer
                echo paginate_links( array(
                    'base' => str_replace( $big, '%#%', esc_url( get_pagenum_link( $big ) ) ),
                    'format' => '?paged=%#%',
                    'current' => max( 1, $paged ),
                    'total' => $reviews_query->max_num_pages,
                ) );
                echo '</div>';
            }
        } else {
            // No reviews found
            echo '<div class="alchemer-reviews-empty">';
            echo '<p>' . __( 'No reviews found.', 'alchemer-reviews' ) . '</p>';
            echo '</div>';
        }
        
        // Restore original post data
        wp_reset_postdata();
        
        // Get the buffer contents and clean buffer
        $output = ob_get_clean();
        
        return $output;
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
            $stars .= '<span class="dashicons dashicons-star-filled"></span>';
        }
        
        // Add empty stars
        for ( $i = $rating + 1; $i <= $max_stars; $i++ ) {
            $stars .= '<span class="dashicons dashicons-star-empty"></span>';
        }
        
        // Add numeric rating
        $stars .= ' <span class="rating-text">(' . $rating . ')</span>';
        
        return $stars;
    }
} 