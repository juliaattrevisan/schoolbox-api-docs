/* add fields */
ALTER TABLE `rubric_mark`
  ADD COLUMN `id` INT UNSIGNED NOT NULL AUTO_INCREMENT FIRST,
  ADD COLUMN `descriptor_id` INT UNSIGNED AFTER `criterion_id`,
  DROP PRIMARY KEY,
  ADD PRIMARY KEY (`id`);

/* create hard link between marks and descriptors (rubric_mark is now semantically descriptor_selected) */
UPDATE rubric_mark m
INNER JOIN rubric_descriptor d
  ON d.criterion_id = m.criterion_id
  AND d.submission_box_id = m.submission_box_id
  AND d.value = m.mark
SET m.descriptor_id = d.id;

/* allow nullability of marks */
ALTER TABLE `rubric_mark`
  CHANGE `mark` `mark` INT(10) UNSIGNED NULL;

/* null out the marks where we now have descriptor references */
UPDATE rubric_mark
SET mark = NULL
WHERE descriptor_id IS NOT NULL;
