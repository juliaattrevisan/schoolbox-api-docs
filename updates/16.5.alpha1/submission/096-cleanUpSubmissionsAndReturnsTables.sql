-- DROPs the now superfluous columns to remove confusing clutter
ALTER TABLE `student_submission`
  DROP COLUMN `hash`,
  DROP COLUMN `realname`,
  DROP COLUMN `marked`,
  DROP COLUMN `late`,
  DROP COLUMN `draft`,
  DROP COLUMN `uuid`
;

ALTER TABLE `submission_return`
  DROP COLUMN `hash`,
  DROP COLUMN `filename`,
  DROP COLUMN `uuid`,
  DROP COLUMN `submitted`
;


-- Add a foreign key between the Plagscan table and the Submission File table
ALTER TABLE `plagscan_document`
  CHANGE COLUMN `student_submission_file_id` `student_submission_file_id` INT (11) NOT NULL,
  ADD CONSTRAINT `fk_plagscan_document_student_submission_file_id`
  FOREIGN KEY (`student_submission_file_id`)
  REFERENCES `student_submission_file` (`id`)
  ON DELETE RESTRICT
  ON UPDATE RESTRICT;

