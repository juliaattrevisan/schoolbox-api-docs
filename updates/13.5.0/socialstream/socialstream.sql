CREATE TABLE `socialstream_post`( `post_id` INT(11) NOT NULL AUTO_INCREMENT, `parent_id` INT(11), `owner_id` INT(11), `homepage_id` INT(11), `type` VARCHAR(255), `mimetype` VARCHAR(255), `url` VARCHAR(255), `body` VARCHAR(255), `created_at` DATETIME, `updated_at` DATETIME, `deleted_at` DATETIME, PRIMARY KEY (`post_id`) ) CHARSET=utf8 COLLATE=utf8_general_ci;

/* root_id is the post_id
 */
ALTER TABLE `socialstream_post` ADD COLUMN `root_id` INT(11) NULL AFTER `parent_id`;