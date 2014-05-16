-- Clear all route permissions, and set all of them as Instant.
DELETE FROM `msg_route_perm`;
INSERT INTO `msg_route_perm` (`msg_source_id`, `role_id`, `min_level`, `max_level`, `default_level`)
  SELECT `msg_source`.`id`, `groups`.`id`, 0, 300, 250
  FROM `msg_source`
    CROSS JOIN `groups`
  ORDER BY `msg_source`.`id` ASC, `groups`.`id` ASC;
