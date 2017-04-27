ALTER TABLE `user`
  ADD COLUMN `display_grade_graph`
  BOOLEAN NOT NULL DEFAULT TRUE
  AFTER `position_title`;
