-- To enable the addition of foreign keys, we must ensure that all data types match.
-- Specifically that UNSIGNED == UNSIGNED || SIGNED == SIGNED
ALTER TABLE `rubric_criterion`
  MODIFY `id` INT(11) UNSIGNED AUTO_INCREMENT;

ALTER TABLE `rubric_usage`
  MODIFY `submission_box_id` INT(11) UNSIGNED;

ALTER TABLE `rubric_usage`
  MODIFY `criterion_id` INT(11) UNSIGNED;