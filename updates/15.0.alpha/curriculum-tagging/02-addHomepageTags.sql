-- Join table between tags and homepages
CREATE TABLE `content_tag_homepage` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `content_tag_id` int NOT NULL,
    `homepage_id` int NOT NULL,
    `created_at` datetime NOT NULL,
    `modified_at` datetime NOT NULL,
    `deleted_at` datetime DEFAULT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- and its indices
ALTER TABLE `content_tag_homepage` ADD INDEX (`content_tag_id`);
ALTER TABLE `content_tag_homepage` ADD INDEX (`homepage_id`);
ALTER TABLE `content_tag_homepage` ADD INDEX (`deleted_at`);
