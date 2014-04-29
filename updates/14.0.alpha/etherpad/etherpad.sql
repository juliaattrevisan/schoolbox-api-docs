/* Add API key
 */
INSERT INTO `config` (`name`, `value`, `title`, `description`, `field_id`, `type_id`, `editable`) VALUES ('etherpad_api_token', 'xbxs&e#sa8@$2@k73^f32!7wz2djy%', 'Etherpad API Token', 'Used to access Etherpad documents', '1', '10', 'true');

/* Etherpad Pads
 * Add relation table
 */
CREATE TABLE `etherpad_pad`( `id` INT(11) NOT NULL AUTO_INCREMENT, `pad_id` VARCHAR(255) COMMENT 'Etherpad Pad ID', `name` VARCHAR(255) COMMENT 'Pad name', `body` TEXT COMMENT 'Pad body', `created_at` DATETIME, `updated_at` DATETIME, `deleted_at` DATETIME, PRIMARY KEY (`id`) );

/* Lock
 */
ALTER TABLE `etherpad_pad` ADD COLUMN `lock` TINYINT(1) DEFAULT 0 NULL COMMENT 'Whether or not this pad is locked' AFTER `body`;

/* Migrate from old Etherpad Groups table
 */
ALTER TABLE `etherpad_pad` ADD COLUMN `homepage_id` INT(11) NULL COMMENT 'The homepage this pad started in' AFTER `pad_id`;

/* Add environment field
 */
ALTER TABLE `etherpad_pad` ADD COLUMN `environment` VARCHAR(255) NULL AFTER `pad_id`;