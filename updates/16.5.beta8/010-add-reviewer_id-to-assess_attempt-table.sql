-- Adds the User.ID of the reviewer to the Quiz Attempt table
ALTER TABLE `assess_attempt`
  ADD `reviewer_id` INT(11) UNSIGNED NULL DEFAULT NULL AFTER `finish`,
  ADD CONSTRAINT `fk_assess_attempt_reviewer_id`
    FOREIGN KEY (`reviewer_id`)
    REFERENCES `user` (`id`)
    ON DELETE RESTRICT
    ON UPDATE RESTRICT
;

-- Back-fill historical `reviewer_id` for all reviewed attempts with `user`.`id` of Quiz Owner
UPDATE assess_attempt aa
  JOIN assess a ON a.assess_id = aa.assess_id
  JOIN user u ON a.user_id = u.id
SET reviewer_id = a.user_id
WHERE reviewed_at IS NOT NULL;
