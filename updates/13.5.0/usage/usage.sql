/* Start storing the filesize of submissions
 */
ALTER TABLE `student_submission` ADD COLUMN `filesize` INT(11) NULL AFTER `draft`;
ALTER TABLE `submission_return` ADD COLUMN `filesize` INT(11) NULL AFTER `filename`;