/* Start storing the filesize of submissions
 * Start storing filesize and mimetype of returns
 */
ALTER TABLE `student_submission` ADD COLUMN `filesize` INT(11) NULL AFTER `draft`;
ALTER TABLE `submission_return` ADD COLUMN `filesize` INT(11) NULL AFTER `filename`;
ALTER TABLE `submission_return` ADD COLUMN `mime_type` VARCHAR(255) NULL AFTER `filesize`;