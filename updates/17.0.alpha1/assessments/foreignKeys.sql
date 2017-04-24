ALTER TABLE `submission_return_file`
  DROP FOREIGN KEY `submission_return_file_ibfk_1`;

ALTER TABLE `plagscan_document`
  DROP FOREIGN KEY `fk_plagscan_document_student_submission_file_id`;

ALTER TABLE `plagscan_document`
  CHANGE COLUMN `student_submission_file_id` `student_submission_file_id` INT(11) UNSIGNED NOT NULL ;

ALTER TABLE `student_submission_file`
  CHANGE COLUMN `id` `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT ;

ALTER TABLE `submission_return_file`
  CHANGE COLUMN `id` `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT ,
  CHANGE COLUMN `submission_return_id` `submission_return_id` INT(11) UNSIGNED NOT NULL ;


ALTER TABLE `submission_return`
  CHANGE COLUMN `box_id` `box_id` INT(11) UNSIGNED NOT NULL DEFAULT '0' ;

ALTER TABLE `submission_return`
  CHANGE COLUMN `id` `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT ,
  CHANGE COLUMN `box_id` `box_id` INT(11) UNSIGNED NOT NULL DEFAULT '0' ;

ALTER TABLE `student_submission_file`
  CHANGE COLUMN `id` `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT ;


ALTER TABLE `submission_return_file`
  ADD CONSTRAINT `submission_return_file_return_id`
FOREIGN KEY (`submission_return_id`)
REFERENCES `submission_return` (`id`);

ALTER TABLE `submission_return`
  ADD CONSTRAINT `fk_submission_return_box_id`
FOREIGN KEY (`box_id`)
REFERENCES `submission_box` (`id`)
  ON DELETE NO ACTION
  ON UPDATE NO ACTION;

ALTER TABLE `student_submission`
  ADD CONSTRAINT `fk_student_submission_box_id`
FOREIGN KEY (`box_id`)
REFERENCES `submission_box` (`id`)
  ON DELETE NO ACTION
  ON UPDATE NO ACTION;

DELETE plagscan_document FROM plagscan_document
  LEFT JOIN student_submission_file ON student_submission_file_id = student_submission_file.id
WHERE student_submission_file.id is null;

ALTER TABLE `plagscan_document`
  ADD CONSTRAINT `fk_plagscan_document_student_submission_file_id`
FOREIGN KEY (`student_submission_file_id`)
REFERENCES `student_submission_file` (`id`);

DELETE submission_box_file FROM submission_box_file
  LEFT JOIN submission_box ON box_id = submission_box.id
WHERE submission_box.id is null;

ALTER TABLE `submission_box_file`
  ADD CONSTRAINT `fk_submission_box_file_box_id`
FOREIGN KEY (`box_id`)
REFERENCES `submission_box` (`id`)
  ON DELETE NO ACTION
  ON UPDATE NO ACTION;