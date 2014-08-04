-- Fix case of the resource management "days" config option - change to "Days"
UPDATE `component_config_definition` `ccd`
  INNER JOIN `component_definition` `cd` ON `ccd`.`component_definition_id` = `cd`.`id`
  SET `ccd`.`label` = 'Days'
  WHERE `ccd`.`key` = 'days'
  AND `cd`.`class` = 'Schoolbox\\ResourceBooking\\Booking\\Component\\ResourceManagement'
  LIMIT 1;
