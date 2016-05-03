ALTER TABLE `storage`
  DROP COLUMN `mime`,
  DROP COLUMN `uploaded_by`;

ALTER TABLE `file`
  DROP COLUMN `file_size`,
  DROP COLUMN `mime_type`,
  DROP COLUMN `deleted`,
  DROP INDEX `deleted`;

ALTER TABLE `myFile`
  DROP COLUMN `file_size`,
  DROP COLUMN `mime_type`,
  DROP COLUMN `deleted`,
  DROP INDEX `deleted`;

ALTER TABLE `submission_return`
  DROP COLUMN `mime_type`;

ALTER TABLE `student_submission`
  DROP COLUMN `mime_type`,
  DROP COLUMN `body`,
  DROP COLUMN `filesize`;

ALTER TABLE `submission_box`
  DROP COLUMN `fid`,
  DROP INDEX `file`;

ALTER TABLE `dropbox`
  DROP COLUMN `file_size`,
  DROP COLUMN `mime_type`,
  DROP COLUMN `deleted`,
  DROP INDEX `deleted`;

