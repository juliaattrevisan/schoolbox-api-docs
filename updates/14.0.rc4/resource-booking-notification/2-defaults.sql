-- Default values for resource booking messages - all are instant.
INSERT IGNORE INTO `msg_route_perm` (`msg_source_id`, `role_id`, `min_level`, `max_level`, `default_level`)
  SELECT `msg_source`.`id`, `groups`.`id`, 0, 300, 250
  FROM `msg_source`
    INNER JOIN `msg_namespace` ON `msg_source`.`namespace` = `msg_namespace`.`id`
    -- Join source to all roles which have this message source's role type
    INNER JOIN `groups` ON `groups`.`group_type` = `msg_source`.`role_type`
  WHERE `msg_namespace`.`slug` = 'resource_booking'
  ORDER BY `msg_source`.`id` ASC, `groups`.`id` ASC;
