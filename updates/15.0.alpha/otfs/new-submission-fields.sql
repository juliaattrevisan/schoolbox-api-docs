ALTER TABLE `student_submission`
  ADD COLUMN `hash` VARCHAR(40) NOT NULL AFTER `owner`;
ALTER TABLE `submission_return`
  ADD COLUMN `hash` VARCHAR(40) NULL AFTER `owner`;
