-- Update legacy SubmissionBox naming to new IAssessable-compatible relationship naming
RENAME TABLE lu_submission_type TO lu_work_type;

-- Add 'work_type' field to assessment table
ALTER TABLE `assessment`
  ADD COLUMN `work_type_id` INT(4) NULL AFTER `sequence`,
  ADD INDEX `assessment_work_type_id` (`work_type_id`),
  ADD CONSTRAINT `fk_assessment_work_type_id`
  FOREIGN KEY (`work_type_id`)
    REFERENCES `lu_work_type` (`id`)
    ON UPDATE NO ACTION ON DELETE NO ACTION;

-- Copy DueWork workType values
UPDATE assessment a
JOIN submission_box sb ON sb.assessment_id = a.id
SET a.work_type_id = sb.type;

-- Set all Quizzes to 11 (legacy hard-coded value)
UPDATE assessment a
JOIN assess qz ON qz.assessment_id = a.id
SET a.work_type_id = 11;

-- Set all tasks to legacy hard-coded value
UPDATE assessment a
  JOIN task ON task.assessment_id = a.id
SET a.work_type_id = (SELECT id FROM lu_work_type WHERE name like 'Task');

-- Mark field as deprecated (can be deleted in 17.5)
ALTER TABLE submission_box
    CHANGE `type` `type_deprecated` INT(11);
