/* Add config option to allow teachers to upload zip files which can get unpacked
 * into a folder hierarchy. This is found in Resources, not Admin
 */
INSERT INTO `config` (`name`, `value`, `title`, `description`, `field_id`, `options`, `type_id`, `editable`) VALUES ('enable_teacher_zip_upload', '0', 'Enable Teachers Zip Upload', 'If set, staff members will be able to upload zip files to create folder structures', '3', 'true:false', '4', 'true');