-- All steps in the tour (regardless of type)

CREATE TABLE `tour_step`
(
  `uuid`              VARCHAR(36)       PRIMARY KEY NOT NULL,
  `tour_uuid`         VARCHAR(36)       NOT NULL,                 -- Mandatory, step has to belong to a tour
  `next_step_uuid`    VARCHAR(36)       NULL,                     -- Not mandatory, could be final step
  `author_user_id`    INT(4) UNSIGNED   NULL,                     -- Not mandatory, Alaress created tours will not have an author ID
  `type`              ENUM('tooltip')   NOT NULL,                 -- Will be expanded to (tooltip, modal, redirect, etc)
  `created_at`        DATETIME          NOT NULL,
  `updated_at`        DATETIME          NOT NULL,
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
  `tour_step_uuid`    VARCHAR(36)       NOT NULL,                 -- Mandatory, can't have a tooltip floating without a corresponding step
  `title`             VARCHAR(255)      NULL,                     -- Current has no purpose currently- for future use in an admin builder interface
  `body`              TEXT              NULL,                     -- Contents of the tooltip
  `selector`          VARCHAR(255)      NOT NULL,                 -- Tooltips must have a selector (no floating centered tooltips)
  `css_class`         VARCHAR(255)      NULL,                     -- Optional CSS class override for tooltip
  `created_at`        DATETIME          NOT NULL,
  `updated_at`        DATETIME          NOT NULL,
  `deleted_at`        DATETIME          NULL,

  CONSTRAINT `tour_step_tooltip_tour_step_uuid_fk` FOREIGN KEY (`tour_step_uuid`) REFERENCES `tour_step` (`uuid`)
);

CREATE INDEX `tour_step_tooltip_user_id_idx` ON `tour_step_tooltip` (`author_user_id`);
CREATE INDEX `tour_step_tooltip_tour_step_uuid_idx` ON `tour_step_tooltip` (`tour_step_uuid`);

-- JS events on steps

CREATE TABLE `tour_step_event`
(
  `uuid`              VARCHAR(36)             PRIMARY KEY NOT NULL,
  `tour_step_uuid`    VARCHAR(36)             NOT NULL,               -- Mandatory, need to have an event linked to a step
  `selector`          VARCHAR(255)            NOT NULL,               -- The selector to trigger the event on (eg. #search-input)
  `event`             VARCHAR(255)            NOT NULL,               -- The event to trigger (eg. click)
  `insertion_point`   ENUM('open', 'close')   NOT NULL,               -- When to fire the event, opening of the step or on close of the step
  `order`             TINYINT(1)              NOT NULL,               -- Order sequence of events (when there's multiple events for a single step)
  `created_at`        DATETIME                NOT NULL,
  `updated_at`        DATETIME                NOT NULL,
  `deleted_at`        DATETIME                NULL,

  CONSTRAINT `tour_step_event_tour_step_uuid_fk` FOREIGN KEY (`tour_step_uuid`) REFERENCES `tour_step` (`uuid`)
);

CREATE INDEX `tour_step_event_tour_step_uuid_idx` ON `tour_step_tooltip` (`tour_step_uuid`);