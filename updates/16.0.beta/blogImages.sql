-- Create table to hold blog image gallery pictures

CREATE TABLE `blog_image` (
  `id` INT NOT NULL AUTO_INCREMENT COMMENT '',
  `blog_id` INT NULL COMMENT '',
  `hash` VARCHAR(50) NULL COMMENT '',
  `created_at` DATETIME NULL DEFAULT NULL COMMENT '',
  `updated_at` DATETIME NULL DEFAULT NULL COMMENT '',
  `deleted_at` DATETIME NULL DEFAULT NULL COMMENT '',
  PRIMARY KEY (`id`) COMMENT '') ENGINE=InnoDB CHARACTER SET=utf8;

CREATE TABLE `eportfolio_blog_image` (
  `id` INT NOT NULL AUTO_INCREMENT COMMENT '',
  `blog_id` INT NULL COMMENT '',
  `hash` VARCHAR(50) NULL COMMENT '',
  `created_at` DATETIME NULL DEFAULT NULL COMMENT '',
  `updated_at` DATETIME NULL DEFAULT NULL COMMENT '',
  `deleted_at` DATETIME NULL DEFAULT NULL COMMENT '',
  PRIMARY KEY (`id`) COMMENT '') ENGINE=InnoDB CHARACTER SET=utf8;

-- Normal Blog

ALTER TABLE `blog` CHANGE `cid` `folder_id` INT(6) UNSIGNED NOT NULL;
ALTER TABLE `blog` CHANGE `created` `created_at` DATETIME NOT NULL;
ALTER TABLE `blog` CHANGE `deleted` `deleted_at` DATETIME;
ALTER TABLE `blog` CHANGE `owner` `owner_id` INT(6) UNSIGNED NOT NULL;

ALTER TABLE `blogComment` RENAME TO `blog_comment`;
ALTER TABLE `blog_comment` CHANGE `bid` `blog_id` INT(6) UNSIGNED NOT NULL;
ALTER TABLE `blog_comment` CHANGE `owner` `owner_id` INT(6) UNSIGNED NOT NULL;
ALTER TABLE `blog_comment` CHANGE `created` `created_at` DATETIME NOT NULL;
ALTER TABLE `blog_comment` CHANGE `deleted` `deleted_at` DATETIME;

-- ePortfolio Blogs

ALTER TABLE `eportfolio_blog` CHANGE `created` `created_at` DATETIME NOT NULL;
ALTER TABLE `eportfolio_blog` CHANGE `deleted` `deleted_at` DATETIME;
ALTER TABLE `eportfolio_blog` CHANGE `owner` `owner_id` INT(6) UNSIGNED NOT NULL;
ALTER TABLE `eportfolio_blog` ADD `page_id` INT NULL;

ALTER TABLE `eportfolio_blogComment` RENAME TO `eportfolio_blog_comment`
ALTER TABLE `eportfolio_blog_comment` CHANGE `bid` `blog_id` INT(6) UNSIGNED NOT NULL;
ALTER TABLE `eportfolio_blog_comment` CHANGE `owner` `owner_id` INT(6) UNSIGNED NOT NULL;
ALTER TABLE `eportfolio_blog_comment` CHANGE `created` `created_at` DATETIME NOT NULL;
ALTER TABLE `eportfolio_blog_comment` CHANGE `deleted` `deleted_at` DATETIME;

-- Rename component classes

UPDATE `component_definition` SET `class` = 'Schoolbox\\Blog\\Component\\Homepage\\Controller' WHERE `class` = 'Component_Homepage_BlogController';
UPDATE `component_definition` SET `class` = 'Schoolbox\\Blog\\Component\\Eportfolio\\Controller' WHERE `class` = 'Component_Eportfolio_BlogController';
