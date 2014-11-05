-- UTF8MB4 doesn't allow for unique indices past a certain size, so reduce this column size.
ALTER TABLE `curriculum_source`
  CHANGE `name` `name` VARCHAR(100) CHARSET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  ADD UNIQUE INDEX (`name`);

ALTER TABLE `curriculum_learning_area`
  CHANGE `name` `name` VARCHAR(100) CHARSET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  ADD UNIQUE INDEX (`curriculum_source_id`, `name`);

ALTER TABLE `curriculum_learning_level`
  CHANGE `name` `name` VARCHAR(100) CHARSET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  ADD UNIQUE INDEX (`curriculum_source_id`, `name`);
