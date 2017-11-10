ALTER TABLE `assess_attempt`
  ADD INDEX `idx_assess_attempt_finish` (`finish`);

ALTER TABLE assessment
  ADD INDEX idx_close_date (`close_date`);
