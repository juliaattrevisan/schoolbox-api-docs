CREATE TABLE IF NOT EXISTS `user_professionaldevelopment`(
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(4) UNSIGNED NOT NULL,
  `number` VARCHAR(255) NOT NULL,
  `registration_state` ENUM('NSW','VIC','QLD','NT','WA','SA','ACT','TAS') NOT NULL,
  `created_at` DATETIME,
  `updated_at` DATETIME,
  `deleted_at` DATETIME,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_user_id` FOREIGN KEY (`user_id`) REFERENCES `user`(`id`)
);

INSERT IGNORE INTO `error` (`id`, `message`) VALUES ('200', 'Please provide a valid Professional Development Number and Registration State.');