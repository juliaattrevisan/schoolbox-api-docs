ALTER TABLE `plagscan_document` ADD INDEX `file_id` (`file_id`), ADD INDEX `plagscan_id` (`plagscan_id`);
ALTER TABLE `student_submission` ADD INDEX `student` (`owner`);