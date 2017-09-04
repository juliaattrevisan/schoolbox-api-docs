-- Archive log for Crocodoc documents, outside of the student_submission_file table.
CREATE TABLE `student_submission_file_crocodoc_archive_log` (
    `student_submission_file_id` INT(11) UNSIGNED NOT NULL,
    `crocodoc_document_id` VARCHAR(255) CHARACTER SET 'utf8mb4' NOT NULL,
    `status` ENUM('pending', 'processing', 'completed', 'failed') CHARACTER SET 'ascii' NOT NULL DEFAULT 'pending',
    `message` TEXT CHARACTER SET 'utf8mb4'
);

ALTER TABLE `student_submission_file_crocodoc_archive_log`
    ADD UNIQUE INDEX `crocodoc_archive_student_submission_file` (`student_submission_file_id`),
    ADD CONSTRAINT `fk_crocodoc_archive_student_submission_file`
FOREIGN KEY (`student_submission_file_id`)
REFERENCES `student_submission_file` (`id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION;
