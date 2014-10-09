-- Curriculum nodes need a larger node field,
-- because the description might need to be used as a title.
ALTER TABLE `curriculum_node`
  CHANGE `name` `name` VARCHAR(500) CHARSET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL;
