-- Sources for resource booking notifications
INSERT INTO `msg_source` (
    `namespace`,
    `key`,
    `role_type`,
    `description`
  )
  VALUES (
    (SELECT `id` FROM `msg_namespace` WHERE `slug` = 'resource_booking' LIMIT 1),
    'created',
    'parent',
    'somebody books a resource'
  ), (
    (SELECT `id` FROM `msg_namespace` WHERE `slug` = 'resource_booking' LIMIT 1),
    'updated',
    'parent',
    'somebody updates a booking for a resource'
  ), (
    (SELECT `id` FROM `msg_namespace` WHERE `slug` = 'resource_booking' LIMIT 1),
    'approved',
    'parent',
    'somebody approves a booking for a resource'
  ), (
    (SELECT `id` FROM `msg_namespace` WHERE `slug` = 'resource_booking' LIMIT 1),
    'pending',
    'parent',
    'somebody sets a booking for a resource as pending'
  ), (
    (SELECT `id` FROM `msg_namespace` WHERE `slug` = 'resource_booking' LIMIT 1),
    'rejected',
    'parent',
    'somebody rejects a booking for a resource'
  ), (
    (SELECT `id` FROM `msg_namespace` WHERE `slug` = 'resource_booking' LIMIT 1),
    'cancelled',
    'parent',
    'somebody cancels a booking for a resource'
  ), (
  (SELECT `id` FROM `msg_namespace` WHERE `slug` = 'resource_booking' LIMIT 1),
    'created',
    'student',
    'somebody books a resource'
  ), (
  (SELECT `id` FROM `msg_namespace` WHERE `slug` = 'resource_booking' LIMIT 1),
    'updated',
    'student',
    'somebody updates a booking for a resource'
  ), (
  (SELECT `id` FROM `msg_namespace` WHERE `slug` = 'resource_booking' LIMIT 1),
    'approved',
    'student',
    'somebody approves a booking for a resource'
  ), (
  (SELECT `id` FROM `msg_namespace` WHERE `slug` = 'resource_booking' LIMIT 1),
    'pending',
    'student',
    'somebody sets a booking for a resource as pending'
  ), (
  (SELECT `id` FROM `msg_namespace` WHERE `slug` = 'resource_booking' LIMIT 1),
    'rejected',
    'student',
    'somebody rejects a booking for a resource'
  ), (
  (SELECT `id` FROM `msg_namespace` WHERE `slug` = 'resource_booking' LIMIT 1),
    'cancelled',
    'student',
    'somebody cancels a booking for a resource'
  );
