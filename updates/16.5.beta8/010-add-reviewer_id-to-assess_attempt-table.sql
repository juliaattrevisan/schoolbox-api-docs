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
INSERT INTO assess_attempt (
  SELECT
    aa.attempt_id AS attempt_id,
    aa.assess_id AS assess_id,
    aa.user_id AS user_id,
    aa.start AS start,
    aa.finish AS finish,
    IF(aa.reviewed_at IS NULL, NULL, u.id) AS reviewer_id,
    aa.reviewed_at AS reviewed_at,
    aa.deleted_at AS deleted_at
  FROM assess_attempt aa
  JOIN assess a ON aa.assess_id = a.assess_id
  LEFT JOIN user u ON u.id = a.user_id
) ON DUPLICATE KEY UPDATE
  assess_id   = VALUES(assess_id),
  user_id     = VALUES(user_id),
  start       = VALUES(start),
  finish      = VALUES(finish),
  reviewer_id = VALUES(reviewer_id),
  reviewed_at = VALUES(reviewed_at),
  deleted_at  = VALUES(deleted_at);
