CREATE TABLE `tour`
(
  `uuid`              VARCHAR(36)       PRIMARY KEY NOT NULL,
  `author_user_id`    INT(4) UNSIGNED   NULL,
  `title`             VARCHAR(512)      NOT NULL,
  `description`       TEXT              NOT NULL,
  `active`            INT(1) UNSIGNED   NOT NULL,
  `created_at`        DATETIME          NOT NULL,
  `updated_at`        DATETIME          NOT NULL,
  `deleted_at`        DATETIME          NULL
);

CREATE INDEX `tour_author_user_id_idx`	on `tour` (`author_user_id`);
