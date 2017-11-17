CREATE TABLE `user_professionaldevelopment`( `id` INT(11) NOT NULL AUTO_INCREMENT, `user_id` INT(4) UNSIGNED   NOT NULL, `number` VARCHAR(255) NOT NULL, `registration_state` INT(1) NOT NULL, `created_at` DATETIME, `updated_at` DATETIME, `deleted_at` DATETIME, PRIMARY KEY (`id`) );

ALTER TABLE `user_professionaldevelopment` ADD CONSTRAINT `fk_user_id` FOREIGN KEY (`user_id`) REFERENCES `user`(`id`);

INSERT INTO `config` (`name`, `value`, `title`, `description`, `field_id`, `type_id`, `editable`, `hidden`, `required`) VALUES ('enable_professional_development', '0', 'Enable Professional Development', 'Lets staff members maintain their Professional Development details', '3', '1', 'true', 'false', 'false');