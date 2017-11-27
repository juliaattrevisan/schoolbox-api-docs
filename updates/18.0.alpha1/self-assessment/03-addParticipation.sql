-- Create user participation table

CREATE TABLE `participation`
(
  `participation_plan_id`   INT(10) UNSIGNED NOT NULL,
  `user_id`                 INT(4) UNSIGNED NOT NULL,
  `created_at`              DATETIME NOT NULL,
  `updated_at`              DATETIME NOT NULL,

  -- Refer to design notes in SA-5342 and SA-5293 JIRA tickets.
  --
  -- The `deleted_at` flag does not function as a soft-deletion, instead the presence of this date inverts the
  -- logic of this record as follows:
  --
  -- deleted_at = NULL      Implicitly include this user in the participation plan
  -- deleted_at = DATETIME  Implicitly exclude this user from the participation plan (assuming it is dynamic)

  deleted_at DATETIME
);

-- Create composite primary key using user and participation plan IDs

-- As the `deleted_at` flag does not function as a soft-deletion there should never be more than one combination of a
-- participation_plan_id/user_id and there should be no need for an arbitrary integer row ID. if there is this is a data
-- integrity issue as there are potentially conflicting participation rules for the individual

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