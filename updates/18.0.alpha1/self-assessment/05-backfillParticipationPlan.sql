-- Create a dynamic participation plan for each submission_box in the databsae

INSERT INTO       `participation_plan`
                  (
                      assessment_id,
                      is_dynamic,
                      created_at,
                      updated_at
                  )
SELECT            `assessment_id`,                  -- Assessment ID
                  1,                                -- Dynamic participation flag
                  `assessment`.`created_at`,        -- Created timestamp
                  `assessment`.`created_at`         -- Updated timestamp

FROM              `submission_box`
JOIN              `assessment` ON `assessment`.`id` = `submission_box`.`assessment_id`
WHERE             `assessment_id` IN (select `id` FROM `assessment`) AND
                  `assessment_id` NOT IN (select `assessment_id` FROM `participation_plan`);

-- Create a dynamic participation plan for each quiz in the database

INSERT INTO       `participation_plan`
                  (
                      assessment_id,
                      is_dynamic,
                      created_at,
                      updated_at
                  )
SELECT            `assessment_id`,                  -- Assessment ID
                  1,                                -- Dynamic participation flag
                  `assessment`.`created_at`,        -- Created timestamp
                  `assessment`.`created_at`         -- Updated timestamp
FROM              `assess`
JOIN              `assessment` ON `assessment`.`id` = `assess`.`assessment_id`
WHERE             `assessment_id` IN (select `id` FROM `assessment`) AND
                  `assessment_id` NOT IN (select `assessment_id` FROM `participation_plan`);

-- Create a dynamic participation plan for each task in the database

INSERT INTO       `participation_plan`
                  (
                      assessment_id,
                      is_dynamic,
                      created_at,
                      updated_at
                  )
SELECT            `assessment_id`,                  -- Assessment ID
                  1,                                -- Dynamic participation flag
                  `assessment`.`created_at`,        -- Created timestamp
                  `assessment`.`created_at`         -- Updated timestamp
FROM              `task`
JOIN              `assessment` ON `assessment`.`id` = `task`.`assessment_id`
WHERE             `task`.`assessment_id` IN (select `id` FROM `assessment`) AND
                  `assessment_id` NOT IN (select `assessment_id` FROM `participation_plan`);
