-- Create a mapping between submission_box_id and group_id from Turnitin
-- group_id is a hyphen-separated alphanumeric e.g. "b5cf0e76-c1c7-11e8-a355-529269fb1459"
CREATE TABLE `turnitin_group` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `status` ENUM('pending', 'group', 'uploading', 'complete', 'error') NOT NULL,
    `error_code` VARCHAR(255) NULL,
    `error_message` VARCHAR(255) NULL,
    `submission_box_id` INT UNSIGNED NOT NULL,
    `group_id` VARCHAR(255) NULL,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    `deleted_at` DATETIME NULL,
    PRIMARY KEY (`id`),
    INDEX `group_id_idx` (`group_id` ASC),
    CONSTRAINT `fk_turnitin_submission_box_id` FOREIGN KEY (submission_box_id) REFERENCES submission_box (id) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- The Turnitin processing queue and its processing result
-- The last known error is cached here
CREATE TABLE `turnitin_report` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `status` ENUM('pending', 'group', 'submission', 'uploading', 'loaded', 'prepared', 'analysing', 'complete', 'error') NOT NULL,
    `error_code` VARCHAR(255) NULL,
    `error_message` VARCHAR(255) NULL,
    `student_submission_file_id` INT UNSIGNED NOT NULL,
    `turnitin_submission_id` VARCHAR(255) NULL,
    `overall_match_percentage` DECIMAL(10, 2) NULL,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    `deleted_at` DATETIME NULL,
    PRIMARY KEY (`id`),
    INDEX `turnitin_submission_id_idx` (`turnitin_submission_id` ASC),
    CONSTRAINT `fk_turnitin_subfile_id` FOREIGN KEY (student_submission_file_id) REFERENCES student_submission_file (id) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- The Turnitin Group Attachment processing queue and its processing result
-- The last known error is cached here
CREATE TABLE `turnitin_group_attachment` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `status` ENUM('pending', 'group_attachment', 'uploading', 'complete', 'error') NOT NULL,
    `error_code` VARCHAR(255) NULL,
    `error_message` VARCHAR(255) NULL,
    `submission_box_file_id` INT UNSIGNED NOT NULL,
    `turnitin_group_attachment_id` VARCHAR(255) NULL,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    `deleted_at` DATETIME NULL,
    PRIMARY KEY (`id`),
    INDEX `turnitin_group_attachment_id_idx` (`turnitin_group_attachment_id` ASC),
    CONSTRAINT `fk_turnitin_turnitin_group_attachment_id` FOREIGN KEY (submission_box_file_id) REFERENCES submission_box_file (id) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
