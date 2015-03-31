CREATE TABLE `user_link`
  ( `user_id` INT UNSIGNED NOT NULL,
  `linked_user_id` INT UNSIGNED NOT NULL,
  `created_by` INT NULL,
  `created_on` DATETIME NULL,
  PRIMARY KEY (`user_id`, `linked_user_id`)
  );
