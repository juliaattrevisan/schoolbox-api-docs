/**
 * Quiz table indices and FKs
 */

ALTER TABLE `assess` ENGINE = InnoDB;

ALTER TABLE `assess`
  ADD UNIQUE INDEX `fk_assess_assessment_id_idx` (`assessment_id` ASC);
ALTER TABLE `assess`
  ADD CONSTRAINT `fk_assess_assessment_id`
FOREIGN KEY (`assessment_id`)
REFERENCES `assessment` (`id`)
  ON DELETE NO ACTION
  ON UPDATE NO ACTION;
-- No folder required any more
ALTER TABLE `assess`
  CHANGE COLUMN `category_id` `category_id` INT(11) NULL DEFAULT NULL;


/**
 * Due work table indices and FKs
 */

ALTER TABLE `submission_box` ENGINE = InnoDB ;

ALTER TABLE `submission_box`
  ADD UNIQUE INDEX `fk_submission_box_assessment_id_idx` (`assessment_id` ASC);
ALTER TABLE `submission_box`
  ADD CONSTRAINT `fk_submission_box_assessment_id`
FOREIGN KEY (`assessment_id`)
REFERENCES `assessment` (`id`)
  ON DELETE NO ACTION
  ON UPDATE NO ACTION;
-- No folder required any more
ALTER TABLE `submission_box`
  CHANGE COLUMN `category` `category` INT(5) UNSIGNED NULL DEFAULT NULL;


/**
 * Task table indices and FKs
 */

ALTER TABLE `task`
  ADD UNIQUE INDEX `fk_task_assessment_id_idx` (`assessment_id` ASC);
ALTER TABLE `task`
  ADD CONSTRAINT `fk_task_assessment_id`
FOREIGN KEY (`assessment_id`)
REFERENCES `assessment` (`id`)
  ON DELETE NO ACTION
  ON UPDATE NO ACTION;
-- No folder required any more
ALTER TABLE `task`
  CHANGE COLUMN `category_id` `category_id` INT(11) NULL DEFAULT NULL;
