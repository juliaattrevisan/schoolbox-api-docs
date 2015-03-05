-- Create the tag table
-- Generic for now, might make more specific later
CREATE TABLE `content_tag` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `content_type` varchar(32) NOT NULL,
  `content_id` int NOT NULL,
  `tag_type` varchar(32) NOT NULL,
  `tag_id` int NOT NULL,
  `created_at` datetime NOT NULL,
  `modified_at` datetime NOT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- and its indices
ALTER TABLE `content_tag` ADD INDEX (`content_type`, `content_id`);
ALTER TABLE `content_tag` ADD INDEX (`tag_type`, `tag_id`);
