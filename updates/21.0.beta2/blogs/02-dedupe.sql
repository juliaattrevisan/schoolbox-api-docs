-- Dedupe eportfolio blog images
DELETE t1 FROM eportfolio_component_blog_image t1
INNER JOIN eportfolio_component_blog_image t2
WHERE t1.id < t2.id
  AND t1.blog_id = t2.blog_id
  AND t1.`hash` = t2.`hash`;

-- Dedupe homepage blog images
DELETE t1 FROM blog_image t1
INNER JOIN blog_image t2
WHERE t1.id < t2.id
  AND t1.blog_id = t2.blog_id
  AND t1.`hash` = t2.`hash`;