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
    'somebody books a resource'
  ), (
    (SELECT `id` FROM `msg_namespace` WHERE `slug` = 'resource_booking' LIMIT 1),
    'updated',
    'staff',
    'somebody updates a booking for a resource'
  ), (
    (SELECT `id` FROM `msg_namespace` WHERE `slug` = 'resource_booking' LIMIT 1),
    'approved',
    'staff',
    'somebody approves a booking for a resource'
  ), (
    (SELECT `id` FROM `msg_namespace` WHERE `slug` = 'resource_booking' LIMIT 1),
    'pending',
    'staff',
    'somebody sets a booking for a resource as pending'
  ), (
    (SELECT `id` FROM `msg_namespace` WHERE `slug` = 'resource_booking' LIMIT 1),
    'rejected',
    'staff',
    'somebody rejects a booking for a resource'
  ), (
    (SELECT `id` FROM `msg_namespace` WHERE `slug` = 'resource_booking' LIMIT 1),
    'cancelled',
    'staff',
    'somebody cancels a booking for a resource'
  );
