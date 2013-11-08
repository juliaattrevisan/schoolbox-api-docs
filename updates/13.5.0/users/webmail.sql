ALTER TABLE `user` ADD COLUMN `webmail` TINYINT(3) UNSIGNED DEFAULT 1 NOT NULL AFTER `local_email`;

UPDATE `user` SET webmail = local_email;