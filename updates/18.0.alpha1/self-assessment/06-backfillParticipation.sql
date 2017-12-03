-- Find all submission box returns and create a participation record for the owner (student) of each return

INSERT INTO       `participation`
                  (
                      `participation_plan_id`,
                      `user_id`,
                      `created_at`,
                      `updated_at`
                  )
SELECT            `participation_plan`.`id`,                -- Participation plan
                  `student_submission`.`owner`,             -- Owner/student
                  MIN(`student_submission`.`created_at`),   -- Created timestamp (first un-deleted submission date)
                  MIN(`student_submission`.`created_at`)    -- Updated timestamp
FROM              `submission_box`
JOIN              `student_submission` ON `student_submission`.`box_id` = `submission_box`.`id`
JOIN              `participation_plan` ON `participation_plan`.`assessment_id` = `submission_box`.`assessment_id`
LEFT JOIN         `participation` ON `participation`.`participation_plan_id` = `participation_plan`.`id` AND
                                     `participation`.`user_id` = `student_submission`.`owner`
WHERE             `participation`.`participation_plan_id` IS NULL AND
                  `submission_box`.`deleted` = 0
GROUP BY          `participation_plan`.`id`,
                  `student_submission`.`owner`;

-- Find all quiz attempts and create a participation record for the student

INSERT INTO       `participation`
                  (
                      `participation_plan_id`,
                      `user_id`,
                      `created_at`,
                      `updated_at`
                  )
SELECT            `participation_plan`.`id`,                -- Participation plan
                  `assess_attempt`.`user_id`,               -- Student
                  MIN(`assess_attempt`.`start`),            -- Created timestamp (first un-deleted attempt date)
                  MIN(`assess_attempt`.`start`)             -- Updated timestamp
FROM              `assess_attempt`
JOIN              `assess` ON `assess`.`assess_id` = `assess_attempt`.`assess_id`
JOIN              `participation_plan` ON `participation_plan`.`assessment_id` = `assess`.`assessment_id`
LEFT JOIN         `participation` ON `participation`.`participation_plan_id` = `participation_plan`.`id` AND
                                     `participation`.`user_id` = `assess_attempt`.`user_id`
WHERE             `participation`.`participation_plan_id` IS NULL AND
                  `assess_attempt`.`deleted_at` IS NULL
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
SELECT            `participation_plan`.`id`,                -- Participation plan
                  `task_completion`.`student_id`,           -- Student
                  MIN(`task_completion`.`completion_date`), -- Created timestamp (first un-deleted completion date)
                  MIN(`task_completion`.`completion_date`)  -- Updated timestamp
FROM              `task_completion`
JOIN              `task` ON `task`.`id` = `task_completion`.`task_id`
JOIN              `participation_plan` ON `participation_plan`.`assessment_id` = `task`.`assessment_id`
LEFT JOIN         `participation` ON `participation`.`participation_plan_id` = `participation_plan`.`id`
                                     AND `participation`.`user_id` = `task_completion`.`student_id`
WHERE             `participation`.`participation_plan_id` IS NULL AND
                  `task_completion`.`deleted_at` IS NULL
GROUP BY          `participation_plan`.`id`,
                  `task_completion`.`student_id`;