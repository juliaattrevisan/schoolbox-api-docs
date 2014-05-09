-- Namespace for resource booking notifications
INSERT INTO `msg_namespace`
  (`slug`, `label`)
  VALUES
  ('resource_booking', 'Resource Booking');

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
    'staff',
    'somebody booked a resource'
  ), (
    (SELECT `id` FROM `msg_namespace` WHERE `slug` = 'resource_booking' LIMIT 1),
    'updated',
    'staff',
    'somebody updated a booking for a resource'
  ), (
    (SELECT `id` FROM `msg_namespace` WHERE `slug` = 'resource_booking' LIMIT 1),
    'approved',
    'staff',
    'somebody approved a booking for a resource'
  ), (
    (SELECT `id` FROM `msg_namespace` WHERE `slug` = 'resource_booking' LIMIT 1),
    'rejected',
    'staff',
    'somebody rejected a booking for a resource'
  ), (
    (SELECT `id` FROM `msg_namespace` WHERE `slug` = 'resource_booking' LIMIT 1),
    'cancelled',
    'staff',
    'somebody cancelled a booking for a resource'
  );
