SET sql_mode = '';

/* Add the new column */
ALTER TABLE `mobile_app_registry`
  ADD COLUMN `app_id` INT(10) UNSIGNED NOT NULL AFTER user_id,
  CHARACTER SET ASCII COLLATE ascii_general_ci;
