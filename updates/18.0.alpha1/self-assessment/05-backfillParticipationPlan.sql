-- Create a dynamic participation plan for each submission_box in the database

INSERT INTO       `participation_plan`
                  (
                      assessment_id,
                      is_dynamic,
                      created_at,
                      updated_at
                  )
SELECT            `assessment_id`,                                                                -- Assessment ID
                  1,                                                                              -- Dynamic participation flag
                  `assessment`.`created_at`,                                                      -- Created timestamp
                  `assessment`.`created_at`                                                       -- Updated timestamp
FROM              `submission_box`
JOIN              `assessment` ON `assessment`.`id` = `submission_box`.`assessment_id`
WHERE             `assessment_id` NOT IN (select `assessment_id` FROM `participation_plan`) AND   -- Exclude if participation plan already exists
                  `submission_box`.`deleted` = 0 AND                                              -- Exclude if submission box soft-deleted
                  `assessment`.`deleted_at` IS NULL;                                              -- Exclude if assessment soft-deleted

-- Create a dynamic participation plan for each submission_return in the database

INSERT INTO       `participation_plan`
                  (
                      assessment_id,
                      is_dynamic,
                      created_at,
                      updated_at
                  )
SELECT            `assessment_id`,                                                                -- Assessment ID
                  1,                                                                              -- Dynamic participation flag
                  `assessment`.`created_at`,                                                      -- Created timestamp
                  `assessment`.`created_at`                                                       -- Updated timestamp
FROM              `submission_return`
JOIN              `submission_box` ON `submission_box`.`id` = `submission_return`.`box_id`        -- Join onto submission box as we need the assessment ID
JOIN              `assessment` ON `assessment`.`id` = `submission_box`.`assessment_id`
WHERE             `assessment_id` NOT IN (select `assessment_id` FROM `participation_plan`) AND   -- Exclude if participation plan already exists
                  `submission_return`.`deleted_at` IS NULL AND                                    -- Exclude if submission return soft-deleted
                  `submission_box`.`deleted` = 0 AND                                              -- Exclude if submission box soft-deleted
                  `assessment`.`deleted_at` IS NULL;                                              -- Exclude if assessment soft-deleted


-- Create a dynamic participation plan for each quiz in the database

INSERT INTO       `participation_plan`
                  (
                      assessment_id,
                      is_dynamic,
                      created_at,
                      updated_at
                  )
SELECT            `assessment_id`,                                                                -- Assessment ID
                  1,                                                                              -- Dynamic participation flag
                  `assessment`.`created_at`,                                                      -- Created timestamp
                  `assessment`.`created_at`                                                       -- Updated timestamp
FROM              `assess`
JOIN              `assessment` ON `assessment`.`id` = `assess`.`assessment_id`
WHERE             `assessment_id` NOT IN (select `assessment_id` FROM `participation_plan`) AND   -- Exclude if participation plan already exists
                  `assess`.`deleted_at` IS NULL AND                                               -- Exclude if quiz soft-deleted
                  `assessment`.`deleted_at` IS NULL;                                              -- Exclude if assessment soft-deleted

-- Create a dynamic participation plan for each task in the database

INSERT INTO       `participation_plan`
                  (
                      assessment_id,
                      is_dynamic,
                      created_at,
                      updated_at
                  )
SELECT            `assessment_id`,                                                                -- Assessment ID
                  1,                                                                              -- Dynamic participation flag
                  `assessment`.`created_at`,                                                      -- Created timestamp
                  `assessment`.`created_at`                                                       -- Updated timestamp
FROM              `task`
JOIN              `assessment` ON `assessment`.`id` = `task`.`assessment_id`
WHERE             `assessment_id` NOT IN (select `assessment_id` FROM `participation_plan`) AND   -- Exclude if participation plan already exists
                  `task`.`deleted_at` IS NULL AND                                                 -- Exclude if task deleted
                  `assessment`.`deleted_at` IS NULL;                                              -- Exclude if assessment deleted
