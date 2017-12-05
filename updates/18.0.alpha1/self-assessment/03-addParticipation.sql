-- Create user participation table

CREATE TABLE `participation`
(
  `id`                      INT(10) UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  `participation_plan_id`   INT(10) UNSIGNED NOT NULL,
  `user_id`                 INT(4) UNSIGNED NOT NULL,
  `created_at`              DATETIME NOT NULL,
  `updated_at`              DATETIME NOT NULL,
  `excluded_at`             DATETIME
);

-- Add unique index to plan_id+user_id (ie a user can only be in any given plan 0 or 1 times)
ALTER TABLE participation
  ADD UNIQUE INDEX idx_participation_plan_id_user_id (participation_plan_id, user_id);

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
