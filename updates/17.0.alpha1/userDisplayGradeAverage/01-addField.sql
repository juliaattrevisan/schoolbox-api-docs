ALTER TABLE `user`
  ADD COLUMN `display_grade_average`
  BOOLEAN NOT NULL DEFAULT TRUE
  AFTER `position_title`;
