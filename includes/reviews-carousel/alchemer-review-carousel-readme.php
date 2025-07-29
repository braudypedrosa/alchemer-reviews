<?php
/**
* Alchemer Review Carousel - Documentation
* 
* This file provides comprehensive documentation for the Alchemer Review Carousel plugin.
* It includes information about shortcodes, parameters, usage examples, and more.
* 
* Version: 1.3.0
* Author: Braudy Pedrosa
*/

/*
=== Alchemer Review Carousel ===
Version: 1.3.0
Author: Braudy Pedrosa

== Description ==

Alchemer Review Carousel is a WordPress plugin that displays customer reviews from the "Reviews" post type in multiple layouts. The plugin offers three different display options: list, grid, and testimonial carousel.

== Shortcodes ==

The plugin provides three main shortcodes to display reviews in different layouts:

1. List Layout
  [alchemer_reviews_list]
  Displays reviews in a vertical list format.

2. Grid Layout
  [alchemer_reviews_grid]
  Displays reviews in a grid layout with navigation arrows.

3. Testimonial Carousel
  [alchemer_reviews_testimonial]
  Displays reviews in a testimonial carousel with smooth animations.

== Parameters ==

All shortcodes accept the following common parameters:

1. title
  Description: The heading text displayed above the reviews.
  Default: "What Our Customers Say" (list/grid) or "Customer Testimonials" (testimonial)
  Example: [alchemer_reviews_list title="Client Feedback"]

2. count
  Description: The number of reviews to display. Use -1 to show all reviews.
  Default: 3
  Example: [alchemer_reviews_grid count="5"]

3. demo
  Description: Whether to use demo data instead of actual reviews.
  Default: false
  Example: [alchemer_reviews_testimonial demo="true"]

Testimonial Carousel Specific Parameters:

4. slides_to_show
  Description: The number of slides to display at once. Responsive design will adjust this on smaller screens.
  Default: 1
  Example: [alchemer_reviews_testimonial slides_to_show="3"]

5. center_mode
  Description: Whether to enable center mode, which highlights the active slide and shows partial views of adjacent slides.
  Default: false
  Example: [alchemer_reviews_testimonial center_mode="true"]

== Usage Examples ==

Basic Usage:
[alchemer_reviews_list]
[alchemer_reviews_grid]
[alchemer_reviews_testimonial]

With Common Parameters:
[alchemer_reviews_list title="Customer Feedback" count="5"]
[alchemer_reviews_grid title="Recent Reviews" count="6" demo="false"]
[alchemer_reviews_testimonial title="What People Say" count="-1" demo="true"]

Testimonial Carousel with Multiple Slides:
[alchemer_reviews_testimonial slides_to_show="3" title="Customer Testimonials"]
This will display 3 testimonials at once in the carousel.

Testimonial Carousel with Center Mode:
[alchemer_reviews_testimonial center_mode="true" title="Featured Reviews"]
This will enable center mode, highlighting the active slide.

Combining Multiple Parameters:
[alchemer_reviews_testimonial slides_to_show="3" center_mode="true" title="What Our Clients Say" count="9" demo="true"]
This will display 3 demo testimonials at once with center mode enabled.

In a Template File:
<?php echo do_shortcode('[alchemer_reviews_testimonial slides_to_show="3" center_mode="true"]'); ?>

== Review Data Structure ==

The plugin pulls data from the "Reviews" post type with the following structure:

- Review Content: Post content
- Reviewer Name: Post title
- Rating: Post meta "_alchemer_rating" (1-5)
- Review Date: Post meta "_alchemer_review_date" (YYYY-MM-DD format)

== Demo Data ==

The plugin includes demo data that can be displayed by setting the demo parameter to true. This is useful for testing or when you don't have any reviews yet.

[alchemer_reviews_list demo="true"]

== Layout Details ==

List Layout:
The list layout displays reviews in a vertical list with:
- Reviewer name and date
- Star rating
- Review content

Grid Layout:
The grid layout displays reviews in a responsive grid with:
- Reviewer name and date
- Star rating
- Truncated review content
- Navigation arrows (if more than 3 reviews)

Testimonial Carousel:
The testimonial carousel displays reviews with:
- Reviewer name and date
- Star rating
- Full review content
- Navigation arrows and dots
- Smooth animations between slides
- Auto-rotation (pauses on hover)
- Option to display multiple slides at once
- Option to enable center mode for highlighting active slides

== Responsive Behavior ==

The plugin is fully responsive and will adjust to different screen sizes:
- On screens smaller than 992px, 4-column layouts will become 3-column layouts
- On screens smaller than 768px, grid layouts will become single-column, and 3 or 4-column carousels will become 2-column
- On screens smaller than 576px, all carousel layouts will display a single slide regardless of the slides_to_show setting

== Styling ==

The plugin includes default styling that should work with most WordPress themes. If you want to customize the appearance, you can add custom CSS to your theme or use a custom CSS plugin.

CSS Classes:

Common Classes:
- .alchemer-reviews - Main container for all layouts
- .alchemer-reviews-title - Title heading
- .alchemer-star - Star rating icons
- .alchemer-nav-button - Navigation buttons

List Layout Classes:
- .alchemer-reviews-list - List layout container
- .alchemer-review-item - Individual review container
- .alchemer-reviewer-name - Reviewer name
- .alchemer-review-date - Review date
- .alchemer-review-rating - Rating container
- .alchemer-review-content - Review content

Grid Layout Classes:
- .alchemer-reviews-grid - Grid layout container
- .alchemer-grid-container - Grid items container
- .alchemer-grid-item - Individual grid item
- .alchemer-grid-navigation - Grid navigation container

Testimonial Layout Classes:
- .alchemer-reviews-testimonial - Testimonial layout container
- .alchemer-testimonial-slider - Slider container
- .alchemer-testimonial-track - Track that holds all slides
- .alchemer-testimonial-slide - Individual slide
- .alchemer-testimonial-slide-inner - Inner content of each slide
- .alchemer-testimonial-navigation - Navigation container
- .alchemer-testimonial-dots - Dots container

== Troubleshooting ==

No Reviews Displaying:
If no reviews are displaying, check the following:
1. Ensure you have published reviews in the "Reviews" post type
2. Check that your reviews have the required meta fields (_alchemer_rating and _alchemer_review_date)
3. Try using the demo parameter: [alchemer_reviews_list demo="true"]

Styling Issues:
If you experience styling issues:
1. Check if your theme is overriding the plugin styles
2. Try adding !important to your custom CSS rules
3. Inspect the elements using browser developer tools to identify conflicting styles

JavaScript Issues:
If carousel or grid navigation isn't working:
1. Check your browser console for JavaScript errors
2. Ensure jQuery is loaded on your site
3. Check if there are JavaScript conflicts with other plugins

Multiple Slides Not Working:
If multiple slides aren't displaying correctly:
1. Ensure you have enough reviews to display (at least as many as your slides_to_show value)
2. Check if your screen size is triggering the responsive breakpoints
3. Inspect the slider using browser developer tools to check for CSS conflicts

== Support ==

For support or feature requests, please contact the plugin author:
Author: Braudy Pedrosa
Version: 1.3.0
*/
