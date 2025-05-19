<div class="alchemer-reviews alchemer-reviews-grid">
   <h2 class="alchemer-reviews-title"><?php echo esc_html($atts['title']); ?></h2>
   
   <div class="alchemer-grid-container">
       <?php foreach ($reviews as $index => $review) : ?>
           <div class="alchemer-grid-item" data-index="<?php echo $index; ?>">
               <div class="alchemer-reviewer-info">
                   <div class="alchemer-reviewer-meta">
                       <h3 class="alchemer-reviewer-name"><?php echo esc_html($review['name']); ?></h3>
                       <div class="alchemer-review-date"><?php echo date('F j, Y', strtotime($review['date'])); ?></div>
                   </div>
               </div>
               <div class="alchemer-review-rating">
                   <?php echo $this->generate_stars($review['rating']); ?>
               </div>
               <div class="alchemer-review-content">
                   <?php echo wp_trim_words(wpautop($review['content']), 30, '...'); ?>
               </div>
           </div>
       <?php endforeach; ?>
   </div>
   
   <?php if (count($reviews) > 3) : ?>
   <div class="alchemer-grid-navigation">
       <button class="alchemer-nav-button alchemer-grid-prev" aria-label="Previous reviews">&larr;</button>
       <button class="alchemer-nav-button alchemer-grid-next" aria-label="Next reviews">&rarr;</button>
   </div>
   <?php endif; ?>
</div>
