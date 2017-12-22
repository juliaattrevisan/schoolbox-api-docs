DELETE assess_attempt,assess_response FROM assess_attempt
  JOIN assess_response ON assess_response.attempt_id = assess_attempt.attempt_id
  LEFT JOIN user ON user_id = user.id
WHERE user.id is null;

ALTER TABLE `assess_attempt`
  CHANGE COLUMN `user_id` `user_id` INT(11) UNSIGNED NOT NULL;

ALTER TABLE `schoolbox`.`assess_attempt`
  ADD CONSTRAINT `fk_assess_attempt_user_id`
FOREIGN KEY (`user_id`)
REFERENCES `schoolbox`.`user` (`id`)
  ON DELETE NO ACTION
  ON UPDATE NO ACTION;
