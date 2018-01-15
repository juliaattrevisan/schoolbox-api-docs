ALTER TABLE tour_step
MODIFY COLUMN type enum('tooltip', 'modal');

CREATE TABLE `tour_step_modal`
(
  `uuid`              VARCHAR(36)       PRIMARY KEY NOT NULL,
  `tour_step_uuid`    VARCHAR(36)       NOT NULL,                 -- Mandatory, can't have a tooltip floating without a corresponding step
  `author_user_id`    INT(4) UNSIGNED   NULL,                     -- Not mandatory, Alaress created tooltips will not have an author ID
  `title`             VARCHAR(255)      NULL,                     -- Current has no purpose currently- for future use in an admin builder interface
  `body`              TEXT              NULL,                     -- Contents of the tooltip
  `css_class`         VARCHAR(255)      NULL,                     -- Optional CSS class override for tooltip
  `created_at`        DATETIME          NOT NULL,
  `updated_at`        DATETIME          NOT NULL,
  `deleted_at`        DATETIME          NULL,

  CONSTRAINT `tour_step_modal_user_id_fk` FOREIGN KEY (`author_user_id`) REFERENCES `user` (`id`),
  CONSTRAINT `tour_step_modal_tour_step_uuid_fk` FOREIGN KEY (`tour_step_uuid`) REFERENCES `tour_step` (`uuid`)
);

INSERT INTO `tour_step` (`uuid`, `tour_uuid`, `next_step_uuid`, `author_user_id`, `type`, `created_at`, `updated_at`)
VALUES ('d7956911-f9ad-11e7-97db-08002751fd3c', '81bc678b-f9ab-11e7-97db-08002751fd3c', '81bce408-f9ab-11e7-97db-08002751fd3c', '19', 'modal', '1980-04-07 00:00:00', '1980-04-07 00:00:00');

INSERT INTO `schoolbox`.`tour_step_modal` (`uuid`, `tour_step_uuid`, `author_user_id`, `title`, `body`, `created_at`, `updated_at`, `deleted_at`)
VALUES ('a90666ad-f9ae-11e7-97db-08002751fd3c', 'd7956911-f9ad-11e7-97db-08002751fd3c', '19', 'Welcome to your tour!', 'Welcome to your tour!', '1980-04-07 00:00:00', '1980-04-07 00:00:00', '1980-04-07 00:00:00
');

UPDATE `tour` set `tour_step_uuid` = 'd7956911-f9ad-11e7-97db-08002751fd3c';