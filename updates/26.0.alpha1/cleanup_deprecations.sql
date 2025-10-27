DROP TABLE _blog_image_backup;

ALTER TABLE `user` DROP COLUMN `_hash`;
ALTER TABLE `user` DROP COLUMN `_digest`;

ALTER TABLE `moment` DROP COLUMN `_hash`;
ALTER TABLE `moment` DROP COLUMN `_filename`;
ALTER TABLE `moment` DROP COLUMN `_type`;

ALTER TABLE `profile_field` DROP COLUMN `_click_to_reveal`;

