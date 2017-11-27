-- Create user participation table

CREATE TABLE `participation_exclusion`
(
  `assessment_id`           INT(10) UNSIGNED NOT NULL,
  `user_id`                 INT(4) UNSIGNED NOT NULL,
  `created_at`              DATETIME NOT NULL,
  `updated_at`              DATETIME NOT NULL,
  `deleted_at`              DATETIME
);

-- Create composite primary key using user and assessment ID

ALTER TABLE     `participation_exclusion`
ADD PRIMARY KEY (`assessment_id`, `user_id`);

-- Add foreign key to `user` table

ALTER TABLE     `participation_exclusion`
ADD CONSTRAINT  `participation_exclusion_user_id_fk`
FOREIGN KEY     (`user_id`)
REFERENCES      `user` (`id`);

-- Add foreign key to `assessment` table

ALTER TABLE     `participation_exclusion`
ADD CONSTRAINT  `participation_exclusion_assessment_id_fk`
FOREIGN KEY     (`assessment_id`)
REFERENCES      `assessment` (`id`);