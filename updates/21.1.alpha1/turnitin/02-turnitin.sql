-- Create a mapping between submission_box_id and group_id from Turnitin
-- group_id is a hyphen-separated alphanumeric e.g. "b5cf0e76-c1c7-11e8-a355-529269fb1459"
CREATE TABLE `turnitin_group` (
    `submission_box_id` INT NOT NULL,
    `group_id` VARCHAR(255) NOT NULL
);

-- The Turnitin processing queue and its processing result
-- The last known error is cached here
CREATE TABLE `turnitin_report` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `status` ENUM('pending', 'uploading', 'processing', 'complete', 'error') NOT NULL,
    `error_code` VARCHAR(255) NULL,
    `error_message` VARCHAR(255) NULL,
    `student_submission_file_id` INT NOT NULL,
    `turnitin_submission_id` VARCHAR(255) NULL,
    `overall_match_percentage` DECIMAL NULL,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    `deleted_at` DATETIME NULL,
PRIMARY KEY (`id`));
