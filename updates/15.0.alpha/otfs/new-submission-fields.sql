ALTER TABLE `student_submission`
  ADD COLUMN `hash` VARCHAR(40) NOT NULL AFTER `owner`;
