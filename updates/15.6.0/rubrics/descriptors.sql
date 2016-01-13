/* add fields */
ALTER TABLE `rubric_descriptor`
  ADD COLUMN `id` INT UNSIGNED NOT NULL AUTO_INCREMENT FIRST,
  ADD COLUMN `level_id` INT UNSIGNED NOT NULL AFTER `submission_box_id`,
  DROP PRIMARY KEY,
  ADD PRIMARY KEY (`id`);





/* set the values to be unique per crit per box (and reverse-engineerable) */
UPDATE rubric_descriptor SET level_id = `value`;

/* apply the unique constraint */
ALTER TABLE `rubric_descriptor`
  ADD UNIQUE INDEX (`criterion_id`, `submission_box_id`, `level_id`);
