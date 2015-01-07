CREATE TABLE `mobile_app_registry`(
  `user_id` INT UNSIGNED NOT NULL,
  `app_token` VARCHAR(4096) NOT NULL,
  `provider` ENUM('GCM','APNS') NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `touched_at` TIMESTAMP NOT NULL,
  PRIMARY KEY (`user_id`, `app_token`(255), `provider`),
  INDEX (`app_token`),
  INDEX (`user_id`)
) CHARSET=utf8 COLLATE=utf8_bin;
