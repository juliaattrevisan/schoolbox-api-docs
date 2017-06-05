-- Store your plagscan account's Client ID value here
INSERT INTO `config` (`name`, `title`, `description`, `field_id`, `type_id`, `editable`) VALUES ('plagscan_client_id', 'Plagscan Client ID', "Your Plagscan account's Client ID", '1', '10', 'true');

-- Temp folder for plagscan uploads
INSERT INTO `config` (`name`, `title`, `description`, `field_id`, `type_id`, `editable`) VALUES ('plagscan_upload_tmp_dir', 'Plagscan File Upload', "Temporary cache folder for uploading submissions to Plagscan", '1', '10', 'true');

-- Rename column for consistency
ALTER TABLE `plagscan_document` CHANGE `deleted` `deleted_at` DATETIME NULL;

-- Add Interactive Url column for Mode 7 Report
ALTER TABLE `plagscan_document` ADD COLUMN `interactive_url` VARCHAR(255) NULL AFTER `html`;

-- Add option to allow students to see plagscan reports
INSERT INTO `config` (`name`, `value`, `title`, `description`, `field_id`, `type_id`, `editable`) VALUES ('plagscan_allow_students_report_access', '0', 'Allow Students to see plagiarism reports', 'If enabled, students will have access to plagiarism reports', '3', '15', 'true');

-- Allow plagscan records to remember when they were privatised
ALTER TABLE `plagscan_document` ADD COLUMN `privatised_at` DATETIME NULL AFTER `interactive_url`;