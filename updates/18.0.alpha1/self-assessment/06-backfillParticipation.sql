-- For each DueWork participant, find their earliest student_submission or submission return
INSERT INTO participation(participation_plan_id, user_id, created_at, updated_at)
  SELECT id, owner, MIN(created_at), MIN(created_at) FROM (
    SELECT * FROM (
      SELECT pp.id, ss.owner, ss.created_at
      FROM submission_box sb
      JOIN student_submission ss ON sb.id = ss.box_id
      JOIN participation_plan pp ON pp.assessment_id = sb.assessment_id
      WHERE sb.deleted IS FALSE
      AND ss.deleted_at IS NULL) t1
    UNION
    SELECT * FROM (
      SELECT pp.id, sr.owner, sr.created_at
      FROM submission_box sb
      JOIN submission_return sr ON sb.id = sr.box_id
      JOIN participation_plan pp ON pp.assessment_id = sb.assessment_id
      WHERE sb.deleted IS FALSE
      AND sr.deleted_at IS NULL) t2
  ) t3
  GROUP BY id, owner;


-- Find all quiz attempts and create a participation record for the student

INSERT INTO       `participation`
                  (
                      `participation_plan_id`,
                      `user_id`,
                      `created_at`,
                      `updated_at`
                  )
SELECT            `participation_plan`.`id`,                                                                                                                                    -- Participation plan ID
                  `assess_attempt`.`user_id`,                                                                                                                                   -- Participants user ID
                  MIN(`assess_attempt`.`start`),                                                                                                                                -- Created timestamp (first un-deleted attempt date)
                  MIN(`assess_attempt`.`start`)                                                                                                                                 -- Updated timestamp
FROM              `assess_attempt`
JOIN              `assess`              ON `assess`.`assess_id` = `assess_attempt`.`assess_id`                                                                                  -- Join onto the quiz table because we need the assessment ID
JOIN              `participation_plan`  ON `participation_plan`.`assessment_id` = `assess`.`assessment_id`                                                                      -- Join onto the participation plan as we need its ID to make this happen
LEFT JOIN         `participation`       ON `participation`.`participation_plan_id` = `participation_plan`.`id` AND `participation`.`user_id` = `assess_attempt`.`user_id`       -- Join onto the participation table to make this insert idempotent (primarily for testing purposes- but better safe than sorry in case support runs multiple times)
WHERE             `participation`.`participation_plan_id` IS NULL AND                                                                                                           -- Exclude if there is already a participation record
                  `participation_plan`.`deleted_at` IS NULL AND                                                                                                                 -- Exclude if the participation plan was soft-deleted
                  `assess_attempt`.`deleted_at` IS NULL AND                                                                                                                     -- Exclude if the quiz attempt was soft-deleted
                  `assess`.`deleted_at` IS NULL                                                                                                                                 -- Exclude if the quiz was soft-deleted
GROUP BY          `participation_plan`.`id`,
                  `assess_attempt`.`user_id`;

-- Find all task completions and create a participation record for the student

INSERT INTO       `participation`
                  (
                      `participation_plan_id`,
                      `user_id`,
                      `created_at`,
                      `updated_at`
                  )
SELECT            `participation_plan`.`id`,                                                                                                                                    -- Participation plan ID
                  `task_completion`.`student_id`,                                                                                                                               -- Participants user ID
                  MIN(`task_completion`.`completion_date`),                                                                                                                     -- Created timestamp (first un-deleted task completion date)
                  MIN(`task_completion`.`completion_date`)                                                                                                                      -- Updated timestamp
FROM              `task_completion`
JOIN              `task`                ON `task`.`id` = `task_completion`.`task_id`                                                                                            -- Join into the task table because we need the assessment ID
JOIN              `participation_plan`  ON `participation_plan`.`assessment_id` = `task`.`assessment_id`                                                                        -- Join onto the participation plan as we need its ID to make this happen
LEFT JOIN         `participation`       ON `participation`.`participation_plan_id` = `participation_plan`.`id` AND `participation`.`user_id` = `task_completion`.`student_id`   -- Join onto the participation table to make this insert idempotent (primarily for testing purposes- but better safe than sorry in case support runs multiple times)
WHERE             `participation`.`participation_plan_id` IS NULL AND                                                                                                           -- Exclude if there is already a participation record
                  `participation_plan`.`deleted_at` IS NULL AND                                                                                                                 -- Exclude if the participation plan was soft-deleted
                  `task_completion`.`deleted_at` IS NULL AND                                                                                                                    -- Exclude if the task completion was soft-deleted
                  `task`.`deleted_at` IS NULL                                                                                                                                   -- Exclude if the task was soft-deleted
GROUP BY          `participation_plan`.`id`,
                  `task_completion`.`student_id`;
