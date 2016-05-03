ALTER TABLE `mobile_app_registry`
  ADD COLUMN `device_id` VARCHAR(256) NULL AFTER `app_token`,
  ADD UNIQUE INDEX (`user_id`, `device_id`(255));
