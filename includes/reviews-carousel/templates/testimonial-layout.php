<?php
// Generate a unique ID for this slider instance
$slider_id = 'alchemer-testimonial-' . uniqid();

// Get slides_to_show from shortcode attributes or default to 3
$slides_to_show = isset($atts['slides_to_show']) ? intval($atts['slides_to_show']) : 3;

// Convert center_mode to boolean
$center_mode = isset($atts['center_mode']) ? filter_var($atts['center_mode'], FILTER_VALIDATE_BOOLEAN) : false;
?>
<div class="alchemer-reviews alchemer-reviews-testimonial <?php echo $center_mode ? 'center-mode' : ''; ?>" id="<?php echo esc_attr($slider_id); ?>">
    <h2 class="alchemer-reviews-title"><?php echo esc_html($atts['title']); ?></h2>
    
    <div class="alchemer-testimonial-slider" 
         data-slider-id="<?php echo esc_attr($slider_id); ?>"
         data-center-mode="<?php echo $center_mode ? 'true' : 'false'; ?>"
         data-slides-to-show="<?php echo esc_attr($slides_to_show); ?>">
        
        <div class="alchemer-testimonial-track">
            <?php foreach ($reviews as $index => $review) : 
                // Make data-slide 1-based instead of 0-based
                $slide_number = $index + 1;
            ?>
                <div class="alchemer-testimonial-slide <?php echo ($slide_number === 2) ? 'active center' : ''; ?>" 
                     data-slide="<?php echo $slide_number; ?>">
                    <div class="alchemer-testimonial-slide-inner">
                        <h3 class="alchemer-testimonial-name"><?php echo esc_html($review['name']); ?></h3>
                        <div class="alchemer-testimonial-date"><?php echo date('F j, Y', strtotime($review['date'])); ?></div>
                        <div class="alchemer-testimonial-rating">
                            <?php echo $this->generate_stars($review['rating']); ?>
                        </div>
                        <div class="alchemer-testimonial-content">
                            <?php echo wpautop($review['content']); ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <div class="alchemer-testimonial-navigation">
        <button class="alchemer-nav-button alchemer-testimonial-prev" data-slider-id="<?php echo esc_attr($slider_id); ?>" aria-label="Previous testimonial">&larr;</button>
        <div class="alchemer-testimonial-dots">
            <?php 
            // Calculate the number of dots needed
            $total_dots = max(1, count($reviews) - ($slides_to_show - 1));
            for ($i = 0; $i < $total_dots; $i++) : 
                // Make data-slide 1-based instead of 0-based
                $dot_number = $i + 1;
            ?>
                <button class="alchemer-testimonial-dot <?php echo ($i === 0) ? 'active' : ''; ?>" 
                        data-slider-id="<?php echo esc_attr($slider_id); ?>" 
                        data-slide="<?php echo $dot_number; ?>" 
                        aria-label="Go to testimonial slide <?php echo $dot_number; ?>"></button>
            <?php endfor; ?>
        </div>
        <button class="alchemer-nav-button alchemer-testimonial-next" data-slider-id="<?php echo esc_attr($slider_id); ?>" aria-label="Next testimonial">&rarr;</button>
    </div>
</div>
