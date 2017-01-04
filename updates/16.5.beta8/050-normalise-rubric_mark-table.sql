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


-- Add foreign keys to Rubrics

-- Add foreign keys to 'rubric_criterion'
ALTER TABLE `rubric_criterion`
  MODIFY `id` INT(11) UNSIGNED AUTO_INCREMENT,
  MODIFY `parent_id` INT(11) UNSIGNED,
  ADD CONSTRAINT `fk_rubric_criterion_parent_id`
    FOREIGN KEY (`parent_id`)
    REFERENCES `folder` (`id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION;

-- Add foreign keys to 'rubric_criterion'

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
