-- Delete Orphan data to enable foreign keys


-- Delete descriptors if they are not linked to a criterion AND have no marks
-- (the latter part is technically overkill, but just a safety margin in case to retain ability to recover student marks)
DELETE FROM `rubric_descriptor` WHERE `id` IN (
  SELECT * FROM (
    SELECT rd.id
    FROM rubric_descriptor rd
    LEFT JOIN rubric_criterion rc ON rc.id = rd.criterion_id
    LEFT JOIN rubric_mark rm ON rm.descriptor_id = rd.id
    WHERE rc.id IS NULL
    AND rm.id IS NULL) t
);


-- Delete descriptors and levels if they are linked (both directly and indirectly via `rubric_level`) to sub boxes that don't exist
-- These *should* be safe to delete, they are logically the result of deleting a sub box prior to intro of soft-deletion.
CREATE TABLE temp_descriptors_and_levels(
  rd_id INT(11) UNSIGNED,
  rl_id INT(11) UNSIGNED
);
-- Get all descriptors and levels that are both linked to the same sub box, but the sub box doesn't exist.
INSERT INTO temp_descriptors_and_levels (
  SELECT rd.id, rl.id
  FROM `rubric_descriptor` rd
  JOIN `rubric_level` rl ON rl.id = rd.level_id
  WHERE rd.submission_box_id = rl.box_id
  AND rd.submission_box_id NOT IN (SELECT DISTINCT id FROM submission_box)
);
-- Delete orphan descriptors
DELETE FROM `rubric_descriptor` WHERE `id` IN (
  SELECT DISTINCT rd_id FROM temp_descriptors_and_levels
);
-- Delete orphan levels
DELETE FROM `rubric_level` WHERE `id` IN (
  SELECT DISTINCT rl_id FROM temp_descriptors_and_levels
);
-- Drop temp table
DROP TABLE temp_descriptors_and_levels;


-- DELETE descriptors if belonging to non-existent levels, and not linked to any student marks
DELETE FROM rubric_descriptor WHERE id IN (
  SELECT * FROM (
    SELECT rd.id
    FROM rubric_descriptor rd
    LEFT JOIN rubric_level rl ON rd.level_id = rl.id
    LEFT JOIN rubric_mark rm ON rm.descriptor_id = rd.id
    WHERE rl.id IS NULL
    AND rm.id IS NULL) t
);

-- Add foreign keys to Rubrics

-- Add foreign keys to 'rubric_criterion'
ALTER TABLE `rubric_criterion` MODIFY `id` INT(11) UNSIGNED AUTO_INCREMENT;

-- Add foreign keys to 'rubric_descriptor'
ALTER TABLE `rubric_descriptor`
  DROP INDEX `criterion_id_2`,
  ADD CONSTRAINT `fk_rubric_descriptor_criterion_id`
    FOREIGN KEY (`criterion_id`)
    REFERENCES `rubric_criterion` (`id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION,
  ADD INDEX `level_id` (`level_id`),
  ADD CONSTRAINT `fk_rubric_descriptor_level_id`
    FOREIGN KEY (`level_id`)
    REFERENCES `rubric_level` (`id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION;

-- Add foreign keys to 'rubric_criterion'
ALTER TABLE `rubric_level`
  ADD CONSTRAINT `fk_rubric_level_box_id`
  FOREIGN KEY (`box_id`)
  REFERENCES `submission_box` (`id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION;

-- Add foreign keys to 'rubric_mark'
ALTER TABLE `rubric_mark`
  ADD CONSTRAINT `fk_rubric_mark_student_id`
  FOREIGN KEY (`student_id`)
  REFERENCES `user` (`id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION,
  ADD INDEX `descriptor_id` (`descriptor_id`),
  ADD CONSTRAINT `fk_rubric_mark_descriptor_id`
  FOREIGN KEY (`descriptor_id`)
  REFERENCES `rubric_descriptor` (`id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION;

-- Add foreign keys to 'rubric_usage'
ALTER TABLE `rubric_usage`
  MODIFY `submission_box_id` INT(11) UNSIGNED,
  ADD CONSTRAINT `fk_rubric_usage_box_id`
  FOREIGN KEY (`submission_box_id`)
  REFERENCES `submission_box` (`id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION,
  MODIFY `criterion_id` INT(11) UNSIGNED,
  ADD CONSTRAINT `fk_rubric_usage_criterion_id`
  FOREIGN KEY (`criterion_id`)
  REFERENCES `rubric_criterion` (`id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION;


-- Remove redundant fields from 'rubric_mark' table
ALTER TABLE `rubric_mark`
  DROP COLUMN `submission_box_id`,
  DROP COLUMN `criterion_id`,
  DROP COLUMN `mark`
;

-- Remove redundant fields from 'rubric_descriptor' table
ALTER TABLE `rubric_descriptor` DROP COLUMN `submission_box_id`;
