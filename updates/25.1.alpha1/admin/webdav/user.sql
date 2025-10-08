ALTER TABLE `user` CHANGE `hash` `_hash` VARCHAR(100) CHARSET ascii NULL;
ALTER TABLE `user` DROP COLUMN `email_pass`;
ALTER TABLE `user` CHANGE `digest` `_digest` VARCHAR(250) NULL;
