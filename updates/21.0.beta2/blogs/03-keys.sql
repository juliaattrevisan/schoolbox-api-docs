-- Add UNIQUE keys for eportfolio blog images and homepage blog images

ALTER TABLE `eportfolio_component_blog_image`
ADD UNIQUE INDEX `unique_eportfolio_blog_images` (`blog_id` ASC, `hash` ASC);

ALTER TABLE `blog_image`
ADD UNIQUE INDEX `unique_blog_images` (`blog_id` ASC, `hash` ASC);
