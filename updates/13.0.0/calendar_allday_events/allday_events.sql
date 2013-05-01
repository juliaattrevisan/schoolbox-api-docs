-- All day event type
ALTER TABLE `event` ADD `allday` BOOLEAN DEFAULT FALSE;

-- Before this: tasks are all day events, nothing else is.
UPDATE `event` `ev` INNER JOIN `luEventType` `et` ON `ev`.`type`=`et`.`eventTypeId`
SET `ev`.`allday` = TRUE
WHERE `et`.`eventType` = 'Task';