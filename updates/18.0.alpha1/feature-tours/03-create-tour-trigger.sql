-- Triggers for feature tours

CREATE TABLE `tour_trigger`
(
  `uuid`              VARCHAR(36)       PRIMARY KEY,
  `author_user_id`    INT(4) UNSIGNED   NOT NULL,     -- Mandatory, schools have to create these so users should exist
  `tour_uuid`         VARCHAR(36)       NOT NULL,     -- Mandatory, can't have a step without it pointing to a tour
  `selector`          VARCHAR(255)      NOT NULL,     -- Mandatory, can't trigger without a selector
  `event`             VARCHAR(255)      NOT NULL,     -- Mandatory, can't trigger without an event
  `url_regex`         VARCHAR(255)      NULL,         -- Optional, if blank trigger will fire on every URL
  `created_at`        DATETIME          NOT NULL,
  `updated_at`        DATETIME          NOT NULL,
  `deleted_at`        DATETIME          NULL,
  
  CONSTRAINT `tour_trigger_user_id_fk` FOREIGN KEY (`author_user_id`) REFERENCES `user` (`id`),
  CONSTRAINT `tour_trigger_tour_uuid_fk` FOREIGN KEY (`tour_uuid`) REFERENCES `tour` (`uuid`)
);

CREATE INDEX `tour_trigger_user_id_idx` ON `tour_trigger` (`author_user_id`);
CREATE INDEX `tour_trigger_tour_uuid_idx` ON `tour_trigger` (`tour_uuid`);
