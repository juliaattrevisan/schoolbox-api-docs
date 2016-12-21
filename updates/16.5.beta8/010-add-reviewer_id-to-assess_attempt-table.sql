-- Adds the User.ID of the reviewer to the Quiz Attempt table
ALTER TABLE `assess_attempt`
  ADD `reviewer_id` INT(11) UNSIGNED NULL DEFAULT NULL AFTER `finish`,
  ADD CONSTRAINT `fk_assess_attempt_reviewer_id`
    FOREIGN KEY (`reviewer_id`)
    REFERENCES `user` (`id`)
    ON DELETE RESTRICT
    ON UPDATE RESTRICT
;

