/* Etherpad Groups
 * Add relation table
 */
CREATE TABLE `etherpad_groups`( `id` INT(11) NOT NULL AUTO_INCREMENT, `group_id` VARCHAR(255) COMMENT 'Etherpad Group ID', `user_id` INT(11), `homepage_id` INT(11), PRIMARY KEY (`id`) );

/* Add API key
 */
INSERT INTO `config` (`name`, `value`, `title`, `description`, `field_id`, `type_id`, `editable`) VALUES ('etherpad_api_token', 'as90dfuasdifjsdopaifjuhas9p', 'Etherpad API Token', 'Used to access Etherpad documents', '1', '3', 'true');

/* Etherpad Pads
 * Add relation table
 */
CREATE TABLE `etherpad_pad`( `id` INT(11) NOT NULL AUTO_INCREMENT, `pad_id` VARCHAR(255) COMMENT 'Etherpad Pad ID', `group_id` VARCHAR(255) COMMENT 'Etherpad Group ID', `name` VARCHAR(255) COMMENT 'Pad name', `body` TEXT COMMENT 'Pad body', `created_at` DATETIME, `updated_at` DATETIME, `deleted_at` DATETIME, PRIMARY KEY (`id`) );

/* Lock
 */
ALTER TABLE `etherpad_pad` DROP COLUMN `lock`, ADD COLUMN `lock` TINYINT(1) DEFAULT 0 NULL COMMENT 'Whether or not this pad is locked' AFTER `body`;