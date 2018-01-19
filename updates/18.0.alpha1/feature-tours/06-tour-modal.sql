ALTER TABLE tour_step
MODIFY COLUMN type enum('tooltip', 'modal');

CREATE TABLE `tour_step_modal`
(
  `uuid`              VARCHAR(36)       PRIMARY KEY NOT NULL,
  `tour_step_uuid`    VARCHAR(36)       NOT NULL,                 -- Mandatory, can't have a tooltip floating without a corresponding step
  `title`             VARCHAR(255)      NULL,                     -- Current has no purpose currently- for future use in an admin builder interface
  `body`              TEXT              NULL,                     -- Contents of the tooltip
  `css_class`         VARCHAR(255)      NULL,                     -- Optional CSS class override for tooltip
  `created_at`        DATETIME          NOT NULL,
  `updated_at`        DATETIME          NOT NULL,
  `deleted_at`        DATETIME          NULL,

  CONSTRAINT `tour_step_modal_tour_step_uuid_fk` FOREIGN KEY (`tour_step_uuid`) REFERENCES `tour_step` (`uuid`)
);
