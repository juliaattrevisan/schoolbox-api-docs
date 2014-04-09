-- Clear out existing tables
DROP TABLE IF EXISTS `resource_moderator`;
DROP TABLE IF EXISTS `resource_relation`;
DROP TABLE IF EXISTS `resource`;
DROP TABLE IF EXISTS `lu_resource_moderation_type`;
DROP TABLE IF EXISTS `resource_category`;

-- Resource category - required for adding resources
CREATE TABLE `resource_category` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(250) COLLATE utf8_unicode_ci NOT NULL,
  `created_at` DATETIME NOT NULL,
  `modified_at` DATETIME NOT NULL,
  `deleted_at` DATETIME NULL,
  -- Add a LR tree for categories and sub-categories?
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- Resource moderation type - required for adding resources
-- Lookup tables: no need to auto-increment
CREATE TABLE `lu_resource_moderation_type` (
  `id` tinyint NOT NULL,
  `name` varchar(20) COLLATE utf8_unicode_ci NOT NULL,
  `description` text COLLATE utf8_unicode_ci NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
INSERT INTO `lu_resource_moderation_type` (`id`, `name`, `description`) VALUES
  (1, 'Open',      'Any staff member may book this item, and the booking will be automatically confirmed'),
  (2, 'Moderated', 'Any staff member may book this item, but a moderator must confirm the booking'),
  (3, 'Managed',   'Only a moderator may book this item');

-- The actual resource table
CREATE TABLE `resource` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(250) COLLATE utf8_unicode_ci NOT NULL,
  `description` text COLLATE utf8_unicode_ci NOT NULL,
  `resource_category_id` int NOT NULL,
  `resource_moderation_type_id` tinyint NOT NULL DEFAULT 1,
  -- image: link to file upload? Is it a brownhole link, or just an image URL?
  -- Could there be multiple images linked?
  `image_url` VARCHAR(250) NULL DEFAULT NULL,
  `min_lead_time` MEDIUMINT UNSIGNED NOT NULL DEFAULT 0,
  `min_reservation_time` MEDIUMINT UNSIGNED NOT NULL DEFAULT 0,
  -- Max time: let's say 24 hours by default
  `max_reservation_time` MEDIUMINT UNSIGNED NOT NULL DEFAULT 86400,
  `created_at` DATETIME NOT NULL,
  `modified_at` DATETIME NOT NULL,
  `deleted_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  KEY `resource_resource_category_id` (`resource_category_id`),
  CONSTRAINT `resource_resource_category_id`
    FOREIGN KEY (`resource_category_id`)
    REFERENCES `resource_category` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  KEY `resource_resource_moderation_type_id` (`resource_moderation_type_id`),
    CONSTRAINT `resource_resource_moderation_type_id`
    FOREIGN KEY (`resource_moderation_type_id`)
    REFERENCES `lu_resource_moderation_type` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- Relations between resources - a resource may have other resources as extras
-- that may be optionally booked as part of a booking.
CREATE TABLE `resource_relation` (
  `id` int NOT NULL AUTO_INCREMENT,
  `resource_id` int NOT NULL,
  `extra_resource_id` int NOT NULL,
  PRIMARY KEY (`id`),
  KEY `resource_relation_resource_id` (`resource_id`),
    CONSTRAINT `resource_relation_resource_id`
    FOREIGN KEY (`resource_id`)
    REFERENCES `resource` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  KEY `resource_relation_extra_resource_id` (`extra_resource_id`),
  CONSTRAINT `resource_relation_extra_resource_id`
    FOREIGN KEY (`extra_resource_id`)
    REFERENCES `resource` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  UNIQUE KEY (`resource_id`, `extra_resource_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- Resources also have moderator users.
CREATE TABLE `resource_moderator` (
  `id` int NOT NULL AUTO_INCREMENT,
  `resource_id` int NOT NULL,
  `moderator_id` int NOT NULL,
  PRIMARY KEY (`id`),
  KEY `resource_moderator_resource_id` (`resource_id`),
  CONSTRAINT `resource_moderator_resource_id`
    FOREIGN KEY (`resource_id`)
    REFERENCES `resource` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  KEY `resource_moderator_moderator_id` (`moderator_id`),
  -- The user table is still shitty old MYISAM so an actual FK link won't work
#   ,CONSTRAINT `resource_moderator_resource_id`
#     FOREIGN KEY (`moderator_id`)
#     REFERENCES `user` (`id`)
#     ON DELETE CASCADE ON UPDATE CASCADE
  UNIQUE KEY (`resource_id`, `moderator_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
