ALTER TABLE `rubric_usage`
  ADD COLUMN `sequence` INT UNSIGNED DEFAULT 0  NOT NULL AFTER `submission_box_id`;
