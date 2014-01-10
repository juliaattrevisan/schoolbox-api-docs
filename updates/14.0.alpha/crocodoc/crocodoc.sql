/* Temp dir
 */
INSERT INTO `config` (`name`, `value`, `title`, `description`, `field_id`, `type_id`) VALUES ('submission_upload_tmp_dir', '/vagrant/data/cache/submission_upload', 'Submission File Upload', 'Temporary cache folder for uploading submissions to external locations', '1', '3');

/* Crocodoc unique identifiers
 */
ALTER TABLE `student_submission` ADD COLUMN `uuid` VARCHAR(255) NULL AFTER `filesize`;

/* Allow videos to be uploaded through submission system
 */
ALTER TABLE `video_queue` CHANGE `type` `type` ENUM('sbfs','storage','submission') CHARSET utf8 COLLATE utf8_general_ci NOT NULL;