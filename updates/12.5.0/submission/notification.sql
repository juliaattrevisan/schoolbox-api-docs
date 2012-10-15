/* create new table for nofication handling */
CREATE TABLE `submission_notification` (
  `box_id` INT UNSIGNED NOT NULL,
  `type` ENUM('upcoming','overdue','returned') NOT NULL,
  `enabled` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
  `sent` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`box_id`, `type`),
  UNIQUE INDEX (`box_id`),
  UNIQUE INDEX (`type`)
) CHARSET=utf8;
