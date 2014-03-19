-- Clear out existing tables
DROP TABLE IF EXISTS `resource_booking`;
DROP TABLE IF EXISTS `resource_booking_relation`;
DROP TABLE IF EXISTS `lu_resource_booking_status`;

-- Resource booking
CREATE TABLE `resource_booking` (
  `id` int NOT NULL AUTO_INCREMENT,
  `event_id` int NOT NULL,
  `resource_id` int NOT NULL,
  `created_at` DATETIME NOT NULL,
  `modified_at` DATETIME NOT NULL,
  `deleted_at` DATETIME NULL,
  -- Moderator who approved or rejected the booking
  `approved_by` int NULL,
  `rejected_by` int NULL,
  -- Resource index and FK
  KEY `resource_booking_resource_id` (`resource_id`),
  CONSTRAINT `resource_booking_resource_id`
  FOREIGN KEY (`resource_id`)
  REFERENCES `resource` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  -- Event index
  -- The event table is still MYISAM so an actual FK link won't work
  KEY `resource_booking_event_id` (`event_id`),
#   CONSTRAINT `resource_booking_event_id`
#     FOREIGN KEY (`event_id`)
#     REFERENCES `event` (`id`)
#     ON DELETE CASCADE ON UPDATE CASCADE
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- Resource moderation type - required for adding resources
-- Lookup tables: no need to auto-increment
CREATE TABLE `lu_resource_booking_status` (
  `id` tinyint NOT NULL,
  `name` varchar(20) COLLATE utf8_unicode_ci NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
INSERT INTO `lu_resource_booking_status` (`id`, `name`) VALUES
  (1, 'Pending'), (2, 'Approved'), (3, 'Rejected');

-- Relations between resource bookings
CREATE TABLE `resource_booking_relation` (
  `id` int NOT NULL AUTO_INCREMENT,
  `resource_booking_id` int NOT NULL,
  `extra_resource_booking_id` int NOT NULL,
  PRIMARY KEY (`id`),
  KEY `resource_booking_relation_resource_booking_id` (`resource_booking_id`),
  CONSTRAINT `resource_booking_relation_resource_booking_id`
  FOREIGN KEY (`resource_booking_id`)
  REFERENCES `resource_booking` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  KEY `resource_booking_relation_extra_resource_booking_id` (`extra_resource_booking_id`),
  CONSTRAINT `resource_booking_relation_extra_resource_booking_id`
  FOREIGN KEY (`extra_resource_booking_id`)
  REFERENCES `resource_booking` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  UNIQUE KEY (`resource_booking_id`, `extra_resource_booking_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
