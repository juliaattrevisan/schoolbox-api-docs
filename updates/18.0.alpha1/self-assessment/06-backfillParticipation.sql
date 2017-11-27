-- Find all submission box returns and create a participation record for the owner (student) of each return

INSERT INTO       `participation`
                  (
                      `participation_plan_id`,
                      `user_id`,
                      `created_at`,
                      `updated_at`
                  )
SELECT DISTINCT   `participation_plan`.`id`,      -- Participation plan
                  `submission_return`.`owner`,    -- Owner/student
                  CURRENT_TIMESTAMP,              -- Created timestamp
                  CURRENT_TIMESTAMP               -- Updated timestamp
FROM              `submission_box`
JOIN              `submission_return` ON `submission_return`.`box_id` = `submission_box`.`id`
JOIN              `participation_plan` ON `participation_plan`.`assessment_id` = `submission_box`.`assessment_id`;

-- Find all quiz attempts and create a participation record for the student

INSERT INTO       `participation`
                  (
                      `participation_plan_id`,
                      `user_id`,
                      `created_at`,
                      `updated_at`
                  )
SELECT DISTINCT   `participation_plan`.`id`,      -- Participation plan
                  `assess_attempt`.`user_id`,     -- Student
                  CURRENT_TIMESTAMP,              -- Created timestamp
                  CURRENT_TIMESTAMP               -- Updated timestamp
FROM              `assess_attempt`
JOIN              `assess` ON `assess`.`assess_id` = `assess_attempt`.`assess_id`
JOIN              `participation_plan` ON `participation_plan`.`assessment_id` = `assess`.`assessment_id`;

-- Find all task completions and create a participation record for the student

INSERT INTO       `participation`
                  (
                      `participation_plan_id`,
                      `user_id`,
                      `created_at`,
                      `updated_at`
                  )
SELECT DISTINCT   `participation_plan`.`id`,      -- Participation plan
                  `task_completion`.`student_id`, -- Student
                  CURRENT_TIMESTAMP,              -- Created timestamp
                  CURRENT_TIMESTAMP               -- Updated timestamp
FROM              `task_completion`
JOIN              `task` ON `task`.`id` = `task_completion`.`task_id`
JOIN              `participation_plan` ON `participation_plan`.`assessment_id` = `task`.`assessment_id`;
