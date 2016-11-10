-- DROPs the now superfluous columns to remove confusing clutter
ALTER TABLE `student_submission`
  DROP COLUMN `hash`,
  DROP COLUMN `realname`,
  DROP COLUMN `marked`,
  DROP COLUMN `late`,
  DROP COLUMN `uuid`
;

ALTER TABLE `submission_return`
  DROP COLUMN `hash`,
  DROP COLUMN `filename`,
  DROP COLUMN `uuid`,
  DROP COLUMN `submitted`
;

