-- Create table to store the state of Kami upload.
-- A row would be inserted as soon as the uploader attempts to upload.
-- The kami fields would be populated with the Kami API response.
-- The timestamps are standard schoolbox timestamps.
-- Use UNIQUE to prevent multiple rows for any single submission file.
-- Use UNIQUE to prevent a single Kami doc being associated with multiple files.
CREATE TABLE `student_submission_file_kami` (
  `id` INT(11) PRIMARY KEY NOT NULL AUTO_INCREMENT,
  `student_submission_file_id` INT(11) UNSIGNED NOT NULL,
  `kami_document_id` CHAR(32) NULL,
  `status` ENUM ('uploading','processing', 'done', 'error') NOT NULL,
  `error_message` VARCHAR(2048) NULL,
  `kami_created_at` DATETIME NULL,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  `deleted_at` DATETIME NULL,
  UNIQUE INDEX idx_student_submission_file_kami_student_submission_file_id (`student_submission_file_id`),
  CONSTRAINT fk_kami_student_submission_file_id
    FOREIGN KEY (`student_submission_file_id`)
    REFERENCES student_submission_file (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  UNIQUE INDEX idx_student_submission_file_kami_kami_document_id (`kami_document_id`)
) ENGINE=InnoDB;
