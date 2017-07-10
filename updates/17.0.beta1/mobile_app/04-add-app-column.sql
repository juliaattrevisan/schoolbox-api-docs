SET sql_mode = '';

/* Add the new column */
ALTER TABLE `mobile_app_registry`
  ADD COLUMN `app_id` VARCHAR(128) NOT NULL,
  CHARACTER SET ASCII COLLATE ascii_general_ci;
