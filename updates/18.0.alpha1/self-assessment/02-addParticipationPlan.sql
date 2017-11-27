-- Create participation plan table

CREATE TABLE `participation_plan`
(
  `id`                      INT(10) UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  `assessment_id`           INT(10) UNSIGNED NOT NULL,
  `is_dynamic`              TINYINT DEFAULT 0 NOT NULL,
  `description`             TEXT,
  `publish_date`            DATETIME,
  `open_date`               DATETIME,
  `due_date`                DATETIME,
  `close_date`              DATETIME,
  `created_at`              DATETIME NOT NULL,
  `updated_at`              DATETIME NOT NULL,
  `deleted_at`              DATETIME
);

-- Add foreign key to 'assessment' table

ALTER TABLE     `participation_plan`
ADD CONSTRAINT  `participation_plan_assessment_id_fk`
FOREIGN KEY     (`assessment_id`)
REFERENCES      `assessment` (`id`);