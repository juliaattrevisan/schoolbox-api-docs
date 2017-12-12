-- All steps in the tour (regardless of type)

CREATE TABLE `tour_step`
(
  `uuid`              VARCHAR(36)       PRIMARY KEY NOT NULL,
  `tour_uuid`         VARCHAR(36)       NOT NULL,
  `next_step_uuid`    VARCHAR(36)       NULL,
  `author_user_id`    INT(4) UNSIGNED   NOT NULL,
  `type`              ENUM('tooltip')   NOT NULL,
  `created_at`        DATETIME          NULL,
  `updated_at`        DATETIME          NULL,
  `deleted_at`        DATETIME          NULL,

  CONSTRAINT `tour_step_author_user_id_fk` FOREIGN KEY (`author_user_id`) REFERENCES `user` (`id`),
  CONSTRAINT `tour_step_tour_id_fk` FOREIGN KEY (`tour_uuid`) REFERENCES `tour` (`uuid`),
  CONSTRAINT `tour_step_tour_next_step_uuid_fk` FOREIGN KEY (`next_step_uuid`) REFERENCES `tour_step` (`uuid`)
);

CREATE INDEX `tour_step_tour_uuid_idx` ON tour_step (`tour_uuid`);
CREATE INDEX `tour_step_author_user_id_idx` ON `tour_step` (`author_user_id`);
CREATE INDEX `tour_step_tour_next_step_uuid_idx` ON `tour_step` (`next_step_uuid`);

-- Type specific details (Tooltips)

CREATE TABLE `tour_step_tooltip`
(
  `uuid`              VARCHAR(36)       PRIMARY KEY NOT NULL,
  `tour_step_uuid`    VARCHAR(36)       NOT NULL,
  `author_user_id`    INT(4) UNSIGNED   NOT NULL,
  `title`             VARCHAR(255)      NULL,
  `body`              TEXT              NULL,
  `selector`          VARCHAR(255)      NULL,
  `css_class`         VARCHAR(255)      NULL,
  `created_at`        DATETIME          NULL,
  `updated_at`        DATETIME          NULL,
  `deleted_at`        DATETIME          NULL,

  CONSTRAINT `tour_step_tooltip_user_id_fk` FOREIGN KEY (`author_user_id`) REFERENCES `user` (`id`),
  CONSTRAINT `tour_step_tooltip_tour_step_uuid_fk` FOREIGN KEY (`tour_step_uuid`) REFERENCES `tour_step` (`uuid`)
);

CREATE INDEX `tour_step_tooltip_user_id_idx` ON `tour_step_tooltip` (`author_user_id`);
CREATE INDEX `tour_step_tooltip_tour_step_uuid_idx` ON `tour_step_tooltip` (`tour_step_uuid`);
