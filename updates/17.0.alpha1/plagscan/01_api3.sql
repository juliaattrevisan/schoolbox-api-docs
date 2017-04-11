-- Store your plagscan account's Client ID value here
INSERT INTO `config` (`name`, `title`, `description`, `field_id`, `type_id`, `editable`) VALUES ('plagscan_client_id', 'Plagscan Client ID', "Your Plagscan account's Client ID", '1', '10', 'true');

-- Temp folder for plagscan uploads
INSERT INTO `config` (`name`, `title`, `description`, `field_id`, `type_id`, `editable`) VALUES ('plagscan_upload_tmp_dir', 'Plagscan File Upload', "Temporary cache folder for uploading submissions to Plagscan", '1', '10', 'true');
