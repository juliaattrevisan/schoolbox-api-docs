-- Create user participant table

CREATE TABLE `participant`
(
  `id`                      INT(10) UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  `participant_plan_id`   INT(10) UNSIGNED NOT NULL,
  `user_id`                 INT(4) UNSIGNED NOT NULL,
  `created_at`              DATETIME NOT NULL,
  `updated_at`              DATETIME NOT NULL,
  `excluded_at`             DATETIME
);

-- Add unique index to plan_id+user_id (ie a user can only be in any given plan 0 or 1 times)
ALTER TABLE participant
  ADD UNIQUE INDEX idx_participant_plan_id_user_id (participant_plan_id, user_id);

-- Add foreign key to `user` table

ALTER TABLE     `participant`
ADD CONSTRAINT  `participant_user_id_fk`
FOREIGN KEY     (`user_id`)
REFERENCES      `user` (`id`);

-- Add foreign key to `participant_plan` table

ALTER TABLE     `participant`
ADD CONSTRAINT  `participant_participant_plan_id_fk`
FOREIGN KEY     (`participant_plan_id`)
REFERENCES      `participant_plan` (`id`);
