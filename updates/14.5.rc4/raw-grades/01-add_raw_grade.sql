ALTER TABLE `submission_grade`
  ADD COLUMN `raw_grade` INT(11) NOT NULL AFTER `lower_bound`;
