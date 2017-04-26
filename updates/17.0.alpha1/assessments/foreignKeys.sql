-- Delete files linked to missing returns
DELETE srf FROM submission_return_file srf
LEFT JOIN submission_return sr ON sr.id = srf.id
WHERE sr.id IS NULL;

-- Delete files linked to returns linked to missing boxes
DELETE srf FROM submission_return_file srf
JOIN submission_return sr ON sr.id = srf.submission_return_id
LEFT JOIN submission_box sb ON sb.id = sr.box_id
WHERE sb.id IS NULL;

-- Delete returns linked to missing boxes
DELETE sr FROM submission_return sr
LEFT JOIN submission_box sb ON sb.id = sr.box_id
WHERE sb.id IS NULL;

-- Delete files linked to missing submissions
DELETE ssf FROM student_submission_file ssf
LEFT JOIN student_submission ss ON ss.id = ssf.student_submission_id
WHERE ss.id IS NULL;

-- Delete files linked to submissions linked to missing boxes
DELETE ssf FROM student_submission_file ssf
JOIN student_submission ss ON ss.id = ssf.student_submission_id
LEFT JOIN submission_box sb ON sb.id = ss.box_id
WHERE sb.id IS NULL;

-- Delete submissions linked to missing boxes
DELETE ss FROM student_submission ss
LEFT JOIN submission_box sb ON sb.id = ss.box_id
WHERE sb.id IS NULL;

DELETE plagscan_document FROM plagscan_document
LEFT JOIN student_submission_file ON student_submission_file_id = student_submission_file.id
WHERE student_submission_file.id is null;

DELETE submission_box_file FROM submission_box_file
LEFT JOIN submission_box ON box_id = submission_box.id
WHERE submission_box.id is null;

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
  CHANGE COLUMN `box_id` `box_id` INT(11) UNSIGNED NOT NULL;

ALTER TABLE `submission_return`
  CHANGE COLUMN `id` `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT ,
  CHANGE COLUMN `box_id` `box_id` INT(11) UNSIGNED NOT NULL;

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



ALTER TABLE `plagscan_document`
  ADD CONSTRAINT `fk_plagscan_document_student_submission_file_id`
FOREIGN KEY (`student_submission_file_id`)
REFERENCES `student_submission_file` (`id`);



ALTER TABLE `submission_box_file`
  ADD CONSTRAINT `fk_submission_box_file_box_id`
FOREIGN KEY (`box_id`)
REFERENCES `submission_box` (`id`)
  ON DELETE NO ACTION
  ON UPDATE NO ACTION;
