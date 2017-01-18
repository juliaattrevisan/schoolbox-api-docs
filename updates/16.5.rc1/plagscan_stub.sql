-- Allow plagscan stub records to spawn
ALTER TABLE `plagscan_document` CHANGE `plagscan_id` `plagscan_id` INT(11) NULL;
ALTER TABLE `plagscan_document` DROP FOREIGN KEY `fk_plagscan_document_student_submission_file_id`;