/* add fields */
ALTER TABLE `rubric_descriptor`
  ADD COLUMN `id` INT UNSIGNED NOT NULL AUTO_INCREMENT FIRST,
  ADD COLUMN `level_id` INT UNSIGNED NOT NULL AFTER `submission_box_id`,
  DROP PRIMARY KEY,
  ADD PRIMARY KEY (`id`);
