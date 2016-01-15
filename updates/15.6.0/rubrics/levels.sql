/* n:1 lookup of names based on the intersection of a criterion and box */
CREATE TABLE `rubric_level`(
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `box_id` INT UNSIGNED NOT NULL,
  `name` VARCHAR(128) NOT NULL,
  `sequence` INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  INDEX (`box_id`),
  INDEX (`sequence`)
) CHARSET=utf8 COLLATE=utf8_bin;

/* copy the existing "level data" across into the new level table */
INSERT INTO rubric_level (box_id, `name`, sequence)
  SELECT submission_box_id, '', `value`
  FROM rubric_descriptor
  GROUP BY submission_box_id, `value`
  ORDER BY submission_box_id ASC, `value` DESC;

/* now the IDs are injected, reverse those values back onto the descriptor table */
UPDATE rubric_descriptor d
LEFT JOIN rubric_level l ON d.value = l.sequence AND d.submission_box_id = l.box_id
  SET d.level_id = l.id;

/* apply the unique constraint to descriptors now the data is injected */
ALTER TABLE `rubric_descriptor`
  ADD UNIQUE INDEX (`criterion_id`, `submission_box_id`, `level_id`);
