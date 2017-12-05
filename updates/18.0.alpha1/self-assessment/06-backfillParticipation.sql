-- Find all submissions/returns and create a participation record for the student

-- We only want the earliest participation record

SELECT            `submissionUnion`.`id`,
                  `submissionUnion`.`owner`,
                  MIN(`submissionUnion`.`created_at`),
                  MIN(`submissionUnion`.`created_at`)
FROM
(
  -- From either the student_submission table

  SELECT            `participation_plan`.`id`,                                                                                                                                    -- Participation plan ID
                    `student_submission`.`owner`,                                                                                                                                 -- Participants user ID
                    `student_submission`.`created_at`
  FROM              `submission_box`
  JOIN              `student_submission`  ON `student_submission`.`box_id` = `submission_box`.`id`                                                                                -- Join onto the student_submission so we can make sure its not been deleted
  JOIN              `participation_plan`  ON `participation_plan`.`assessment_id` = `submission_box`.`assessment_id`                                                              -- Join onto the participation plan as we need its ID to make this happen
  LEFT JOIN         `participation`       ON `participation`.`participation_plan_id` = `participation_plan`.`id` AND `participation`.`user_id` = `student_submission`.`owner`     -- Join onto the participation table to make this insert idempotent (primarily for testing purposes- but better safe than sorry in case support runs multiple times)
  WHERE             `participation`.`participation_plan_id` IS NULL AND                                                                                                           -- Exclude if there is already a participation record
                    `participation_plan`.`deleted_at` IS NULL AND                                                                                                                 -- Exclude if the participation plan was soft-deleted
                    `submission_box`.`deleted` = 0 AND                                                                                                                            -- Exclude if the submission box was soft-deleted
                    `student_submission`.`deleted_at` IS NULL                                                                                                                     -- Exclude if the student submission was soft-deleted
  GROUP BY          `participation_plan`.`id`,
                    `student_submission`.`owner`

  -- Or the submission_return table

  UNION ALL

  SELECT            `participation_plan`.`id`,                                                                                                                                    -- Participation plan ID
                    `submission_return`.`owner`,                                                                                                                                  -- Participants user ID
                    `submission_return`.`created_at`
  FROM              `submission_return`
  JOIN              `submission_box`      ON `submission_box`.`id` = `submission_return`.`box_id` AND `submission_box`.deleted = 0                                                -- Join onto the submission_box so we can link back to the assessment and because we need the assessment ID
  JOIN              `student_submission`  ON `student_submission`.`box_id` = `submission_return`.`id`                                                                             -- Join onto the submission to make sure that wasn't deleted
  JOIN              `participation_plan`  ON `participation_plan`.`assessment_id` = `submission_box`.`assessment_id`                                                              -- Join onto the participation plan as we need its ID to make this happen
  LEFT JOIN         `participation`       ON `participation`.`participation_plan_id` = `participation_plan`.`id` AND `participation`.`user_id` = `submission_return`.`owner`     -- Join onto the participation table to make this insert idempotent (primarily for testing purposes- but better safe than sorry in case support runs multiple times)
  WHERE             `participation`.`participation_plan_id` IS NULL AND                                                                                                           -- Exclude if there is already a participation record
                    `participation_plan`.`deleted_at` IS NULL AND                                                                                                                 -- Exclude if the participation plan was soft-deleted
                    `submission_box`.`deleted` = 0 AND                                                                                                                            -- Exclude if the submission box was soft-deleted
                    `student_submission`.`deleted_at` IS NULL AND                                                                                                                 -- Exclude if the student submission was soft-deleted
                    `submission_return`.`deleted_at` IS NULL                                                                                                                      -- Exclude if the submission return was soft-deleted
  GROUP BY          `participation_plan`.`id`,
                    `student_submission`.`owner`
)
AS submissionUnion

GROUP BY            `submissionUnion`.`id`,
                    `submissionUnion`.`owner`;

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