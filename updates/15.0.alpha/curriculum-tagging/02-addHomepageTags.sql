-- Join table between tags and folders
CREATE TABLE `content_tag_for_category` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `content_tag_id` int NOT NULL,
    `category_id` int NOT NULL,
    `created_at` datetime NOT NULL,
    `modified_at` datetime NOT NULL,
    `deleted_at` datetime DEFAULT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- and its indices
ALTER TABLE `content_tag_for_category` ADD INDEX (`content_tag_id`);
ALTER TABLE `content_tag_for_category` ADD INDEX (`category_id`);
ALTER TABLE `content_tag_for_category` ADD INDEX (`deleted_at`);
