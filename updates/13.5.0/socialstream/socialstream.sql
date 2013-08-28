CREATE TABLE `socialstream_post`( `post_id` INT(11) NOT NULL AUTO_INCREMENT, `parent_id` INT(11), `owner_id` INT(11), `homepage_id` INT(11), `type` VARCHAR(255), `mimetype` VARCHAR(255), `url` VARCHAR(255), `body` VARCHAR(255), `created_at` DATETIME, `updated_at` DATETIME, `deleted_at` DATETIME, PRIMARY KEY (`post_id`) ) CHARSET=utf8 COLLATE=utf8_general_ci;

/* root_id is the post_id
 */
ALTER TABLE `socialstream_post` ADD COLUMN `root_id` INT(11) NULL AFTER `parent_id`;

/* the place where thumbnail urls are found
 */
ALTER TABLE `socialstream_post` ADD COLUMN `thumbnail` VARCHAR(255) NULL AFTER `mimetype`;

/* Add external_title and external_description columns
 * These are meta attributes derived from linked urls
 */
ALTER TABLE `socialstream_post` ADD COLUMN `external_title` VARCHAR(255) NULL COMMENT 'Title for the external link' AFTER `url`, ADD COLUMN `external_body` VARCHAR(255) NULL COMMENT 'Description for the external link' AFTER `external_title`;

/* Remove obsolete storage_id column
 */
ALTER TABLE `socialstream_post` DROP COLUMN `storage_id`;

/* Add empty-post error message
 */
INSERT INTO `error` (`id`, `message`) VALUES ('157', 'Please compose a message before posting to the SocialStream.');