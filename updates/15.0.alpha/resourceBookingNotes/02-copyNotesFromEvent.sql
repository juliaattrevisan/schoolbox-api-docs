-- Copy notes from event to resource booking
UPDATE `resource_booking` `rb`
    INNER JOIN `event` `ev` ON `rb`.`event_id` = `ev`.`id`
SET `rb`.`notes` = `ev`.`detail`;
