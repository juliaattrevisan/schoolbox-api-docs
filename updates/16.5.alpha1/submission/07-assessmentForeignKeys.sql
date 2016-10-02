/*

 Removed because surveys prevent the foreign key from working

 ALTER TABLE `assess` ENGINE = InnoDB;

ALTER TABLE `assess`
  ADD INDEX `fk_assess_assessment_id_idx` (`assessment_id` ASC);
ALTER TABLE `assess`
  ADD CONSTRAINT `fk_assess_assessment_id`
FOREIGN KEY (`assessment_id`)
REFERENCES `assessment` (`id`)
  ON DELETE NO ACTION
  ON UPDATE NO ACTION;
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

ALTER TABLE `task`
  ADD UNIQUE INDEX `fk_task_assessment_id_idx` (`assessment_id` ASC);
ALTER TABLE `task`
  ADD CONSTRAINT `fk_task_assessment_id`
FOREIGN KEY (`assessment_id`)
REFERENCES `assessment` (`id`)
  ON DELETE NO ACTION
  ON UPDATE NO ACTION;