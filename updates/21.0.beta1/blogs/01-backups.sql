-- Make a copy of the blog image tables
-- and also ram in all the old rows
CREATE TABLE eportfolio_component_blog_image_backup LIKE eportfolio_component_blog_image;
INSERT INTO eportfolio_component_blog_image_backup SELECT * FROM eportfolio_component_blog_image;

CREATE TABLE blog_image_backup LIKE blog_image;
INSERT INTO blog_image_backup SELECT * FROM blog_image;

