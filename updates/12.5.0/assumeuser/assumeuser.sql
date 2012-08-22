CREATE TABLE `user_assume_log`( `id` INT(11) NOT NULL AUTO_INCREMENT, `user` VARCHAR(255), `assumed` VARCHAR(255), `ip` VARCHAR(255), `created_at` DATETIME, PRIMARY KEY (`id`) );
