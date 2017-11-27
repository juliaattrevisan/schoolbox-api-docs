-- Create user participation table

CREATE TABLE `participation`
(
  `participation_plan_id`   INT(10) UNSIGNED NOT NULL,
  `user_id`                 INT(4) UNSIGNED NOT NULL,
  `created_at`              DATETIME NOT NULL,
  `updated_at`              DATETIME NOT NULL,
  `deleted_at`              DATETIME
);

-- Create composite primary key using user and participation plan IDs

ALTER TABLE     `participation`
ADD PRIMARY KEY (`participation_plan_id`, `user_id`);

-- Add foreign key to `user` table

ALTER TABLE     `participation`
ADD CONSTRAINT  `participation_user_id_fk`
FOREIGN KEY     (`user_id`)
REFERENCES      `user` (`id`);

-- Add foreign key to `participation_plan` table

ALTER TABLE     `participation`
ADD CONSTRAINT  `participation_participation_plan_id_fk`
FOREIGN KEY     (`participation_plan_id`)
REFERENCES      `participation_plan` (`id`);