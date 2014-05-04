/* Temp dir
 */
INSERT INTO `config` (`name`, `value`, `title`, `description`, `field_id`, `type_id`) VALUES ('submission_upload_tmp_dir', '/var/lib/schoolbox/cache/submission_upload', 'Submission File Upload', 'Temporary cache folder for uploading submissions to external locations', '1', (SELECT `id` FROM lu_config_type WHERE `type` like 'Paths'));

/* Crocodoc unique identifiers
 */
ALTER TABLE `student_submission` ADD COLUMN `uuid` VARCHAR(255) NULL AFTER `filesize`;

/* Allow videos to be uploaded through submission system
 */
ALTER TABLE `video_queue` CHANGE `type` `type` ENUM('sbfs','storage','submission') CHARSET utf8 COLLATE utf8_general_ci NOT NULL;

/* Shove api token into config table
 */
INSERT INTO `config` (`name`, `value`, `title`, `description`, `field_id`, `type_id`, `editable`) VALUES ('crocodoc_api_token', 'D4t65GknNUXFev8HJlTWpf2V', 'Crocodoc API Token', 'Crocodoc API token for inline grading. Used to download and annotate submissions.', '1', (SELECT `id` FROM lu_config_type WHERE `type` like 'LMS Policy'), 'true');