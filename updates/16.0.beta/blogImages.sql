CREATE TABLE `blog_image` (
  `id` INT NOT NULL AUTO_INCREMENT COMMENT '',
  `blog_id` INT NULL COMMENT '',
  `hash` VARCHAR(50) NULL COMMENT '',
  `created_at` DATETIME NULL DEFAULT NULL COMMENT '',
  `updated_at` DATETIME NULL DEFAULT NULL COMMENT '',
  `deleted_at` DATETIME NULL DEFAULT NULL COMMENT '',
  PRIMARY KEY (`id`) COMMENT '') ENGINE=InnoDB CHARACTER SET=utf8;

ALTER TABLE `blog` CHANGE `cid` `folder_id` INT(6) UNSIGNED NOT NULL;
ALTER TABLE `blog` CHANGE `created` `created_at` DATETIME NOT NULL;
ALTER TABLE `blog` CHANGE `deleted` `deleted_at` DATETIME;
ALTER TABLE `blog` CHANGE `owner` `owner_id` INT(6) UNSIGNED NOT NULL;

ALTER TABLE `blogComment` RENAME TO `blog_comment`;
ALTER TABLE `blog_comment` CHANGE `bid` `blog_id` INT(6) UNSIGNED NOT NULL;
ALTER TABLE `blog_comment` CHANGE `owner` `owner_id` INT(6) UNSIGNED NOT NULL;
ALTER TABLE `blog_comment` CHANGE `created` `created_at` DATETIME NOT NULL;
ALTER TABLE `blog_comment` CHANGE `deleted` `deleted_at` DATETIME;