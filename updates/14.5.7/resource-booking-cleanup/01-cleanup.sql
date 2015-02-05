/**
 * Delete bookings that are related to deleted resources
 */
UPDATE `resource_booking` `r`
  INNER JOIN `resource` `i` ON `r`.`resource_id` = `i`.`id`
  SET `r`.`deleted_at` = `i`.`deleted_at`
  WHERE `i`.`deleted_at` IS NOT NULL;
