/* Remove incorrect indices [SB-1725] */
ALTER TABLE `submission_notification`
  DROP INDEX `box_id`,
  DROP INDEX `type`;