<div class="alchemer-reviews alchemer-reviews-list">
   <h2 class="alchemer-reviews-title"><?php echo esc_html($atts['title']); ?></h2>
   
   <?php foreach ($reviews as $review) : ?>
       <div class="alchemer-review-item">
           <div class="alchemer-review-header">
               <div class="alchemer-reviewer-info">
                   <div class="alchemer-reviewer-meta">
                       <h3 class="alchemer-reviewer-name"><?php echo esc_html($review['name']); ?></h3>
                       <div class="alchemer-review-date"><?php echo date('F j, Y', strtotime($review['date'])); ?></div>
                   </div>
               </div>
               <div class="alchemer-review-rating">
                   <?php echo $this->generate_stars($review['rating']); ?>
               </div>
           </div>
           <div class="alchemer-review-content">
               <?php echo wpautop($review['content']); ?>
           </div>
       </div>
   <?php endforeach; ?>
</div>
