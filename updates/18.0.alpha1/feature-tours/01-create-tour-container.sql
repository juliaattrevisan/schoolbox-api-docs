CREATE TABLE `tour`
(
  `uuid`              VARCHAR(36)       PRIMARY KEY NOT NULL,
  `tour_step_uuid`    VARCHAR(36)       NULL,                   -- Not mandatory, during building a tour- this will be created before any steps
  `title`             VARCHAR(512)      NOT NULL,
  `author_user_id`    INT(4) UNSIGNED   NULL,                   -- Not mandatory, Alaress created tours will not have an author ID
  `description`       TEXT              NULL,                   -- Optional
  `active`            INT(1) UNSIGNED   NOT NULL DEFAULT 0,
  `created_at`        DATETIME          NOT NULL,
  `updated_at`        DATETIME          NOT NULL,
  `deleted_at`        DATETIME          NULL
);

CREATE INDEX `tour_author_user_id_idx`	on `tour` (`author_user_id`);
CREATE INDEX `tour_author_tour_step_uuid_idx`	on `tour` (`tour_step_uuid`);
