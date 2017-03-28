-- Update legacy SubmissionBox naming to new IAssessable-compatible relationship naming
RENAME TABLE lu_submission_type TO lu_work_type;

-- Add 'work_type' field to assessment table
ALTER TABLE `assessment`
  ADD COLUMN `work_type_id` INT(4) NULL AFTER `sequence`,
  ADD INDEX `assessment_work_type_id` (`work_type_id`),
  ADD FOREIGN KEY (`work_type_id`)
    REFERENCES `lu_work_type` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE;

-- Copy DueWork workType values
UPDATE assessment a
JOIN submission_box sb ON sb.assessment_id = a.id
SET a.work_type_id = sb.type;

-- Set all Quizzes to 11 (legacy hard-coded value)
UPDATE assessment a
JOIN assess qz ON qz.assessment_id = a.id
SET a.work_type_id = 11;

-- Mark field as deprecated (can be deleted in 17.5)
ALTER TABLE submission_box
    CHANGE `type` `type_deprecated` INT(11);
