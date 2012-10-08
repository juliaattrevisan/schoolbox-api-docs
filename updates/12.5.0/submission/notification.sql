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

/* remap the existing notification handling into the new table; currently, all the notifications are overdue ones */
INSERT INTO
submission_notification (
  SELECT id AS `box_id`, 'overdue' AS `type`, notif_enabled AS `enabled`, notif_sent AS `sent`
  FROM submission_box sb
);
