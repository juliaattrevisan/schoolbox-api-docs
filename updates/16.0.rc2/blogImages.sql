-- Create table to hold blog image gallery pictures and tidy up table naming

-- Normal Blog

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
ALTER TABLE `blog` ADD COLUMN `updated_at` DATETIME NOT NULL AFTER `created_at`;
UPDATE `blog` SET `updated_at` = `created_at`;

ALTER TABLE `blogComment` RENAME TO `blog_comment`;
ALTER TABLE `blog_comment` CHANGE `bid` `blog_id` INT(6) UNSIGNED NOT NULL;
ALTER TABLE `blog_comment` CHANGE `owner` `owner_id` INT(6) UNSIGNED NOT NULL;
ALTER TABLE `blog_comment` CHANGE `created` `created_at` DATETIME NOT NULL;
ALTER TABLE `blog_comment` CHANGE `deleted` `deleted_at` DATETIME;
ALTER TABLE `blog_comment` ADD COLUMN `updated_at` DATETIME NOT NULL AFTER `created_at`;
UPDATE `blog_comment` SET `updated_at` = `created_at`;

-- ePortfolio Blogs

CREATE TABLE `eportfolio_component_blog_image` (
  `id` INT NOT NULL AUTO_INCREMENT COMMENT '',
  `blog_id` INT NULL COMMENT '',
  `hash` VARCHAR(50) NULL COMMENT '',
  `created_at` DATETIME NULL DEFAULT NULL COMMENT '',
  `updated_at` DATETIME NULL DEFAULT NULL COMMENT '',
  `deleted_at` DATETIME NULL DEFAULT NULL COMMENT '',
  PRIMARY KEY (`id`) COMMENT '') ENGINE=InnoDB CHARACTER SET=utf8;

-- Drop these old tables
DROP TABLE IF EXISTS `eportfolio_blog`;
DROP TABLE IF EXISTS `eportfolio_blogComment`;

-- Update eportfolio blog tables to match blog tables more closely
ALTER TABLE `eportfolio_component_blog`
  CHANGE `uid` `owner_id` INT(11) NULL,
  CHANGE `title` `heading` VARCHAR(255) CHARSET utf8 COLLATE utf8_general_ci NULL,
  CHANGE `content` `body` TEXT CHARSET utf8 COLLATE utf8_general_ci NULL;

ALTER TABLE `eportfolio_component_blogcomment`
  CHANGE `uid` `owner_id` INT(11) NULL,
  CHANGE `content` `body` TEXT CHARSET utf8 COLLATE utf8_general_ci NULL;
ALTER TABLE `eportfolio_component_blogcomment` RENAME TO `eportfolio_component_blog_comment`;

-- Rename component definition class names to reflect newly created classes

UPDATE `component_definition` SET `class` = 'Schoolbox\\Blog\\Component\\Homepage\\Controller' WHERE `class` = 'Component_Homepage_BlogController';
UPDATE `component_definition` SET `class` = 'Schoolbox\\Blog\\Component\\Eportfolio\\Controller' WHERE `class` = 'Component_Eportfolio_BlogController';
