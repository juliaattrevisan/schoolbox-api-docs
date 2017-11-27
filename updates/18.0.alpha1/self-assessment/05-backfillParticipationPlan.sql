-- Create a dynamic participation plan for each submission_box in the databsae

INSERT INTO       `participation_plan`
                  (
                      assessment_id,
                      is_dynamic,
                      created_at,
                      updated_at
                  )
SELECT            `assessment_id`,          -- Assessment ID
                  1,                        -- Dynamic participation flag
                  CURRENT_TIMESTAMP,        -- Created timestamp
                  CURRENT_TIMESTAMP         -- Updated timestamp
FROM              `submission_box`
WHERE             `assessment_id` IN (select `id` FROM `assessment`) AND
                  `assessment_id` NOT IN (select `assessment_id` FROM `participation_plan`);

INSERT INTO       `participation_plan`
                  (
                      assessment_id,
                      is_dynamic,
                      created_at,
                      updated_at
                  )
SELECT            `assessment_id`,          -- Assessment ID
                  1,                        -- Dynamic participation flag
                  CURRENT_TIMESTAMP,        -- Created timestamp
                  CURRENT_TIMESTAMP         -- Updated timestamp
FROM              `assess`
WHERE             `assessment_id` IN (select `id` FROM `assessment`) AND
                  `assessment_id` NOT IN (select `assessment_id` FROM `participation_plan`);


INSERT INTO       `participation_plan`
                  (
                      assessment_id,
                      is_dynamic,
                      created_at,
                      updated_at
                  )
SELECT            `assessment_id`,          -- Assessment ID
                  1,                        -- Dynamic participation flag
                  CURRENT_TIMESTAMP,        -- Created timestamp
                  CURRENT_TIMESTAMP         -- Updated timestamp
FROM              `task`
WHERE             `task`.`assessment_id` IN (select `id` FROM `assessment`) AND
                  `assessment_id` NOT IN (select `assessment_id` FROM `participation_plan`);
