INSERT IGNORE INTO `config` (`name`,`value`,`title`, `description`, `field_id`, `options`, `type_id`, `editable`, `hidden`, `required`)
VALUES (
	  'push_notifications_enabled', '0', 'Enable Push Notification Module', 'Enables push notification module.',
	  (SELECT cf.id FROM lu_config_field cf WHERE cf.field = 'boolean'), '',
	  (SELECT ct.id FROM lu_config_type ct WHERE ct.type LIKE 'modules'),
	  'false', 'false', 'false'
);
INSERT IGNORE INTO `config` (`name`,`value`,`title`, `description`, `field_id`, `options`, `type_id`, `editable`, `hidden`, `required`)
VALUES (
	  'push_gcm_api_key', '', 'Google Cloud Messaging API Key', '', 
	  (SELECT cf.id FROM lu_config_field cf WHERE cf.field = 'input'), '',
	  (SELECT ct.id FROM lu_config_type ct WHERE ct.type LIKE 'modules'),
	  'true', 'false', 'false'
);
INSERT IGNORE INTO `config` (`name`,`value`,`title`, `description`, `field_id`, `options`, `type_id`, `editable`, `hidden`, `required`)
VALUES (
	  'push_apns_cert_path', '', 'Apple Push Notification Service Certificate Path', '',
	  (SELECT cf.id FROM lu_config_field cf WHERE cf.field = 'input'), '',
	  (SELECT ct.id FROM lu_config_type ct WHERE ct.type LIKE 'modules'),
	  'true', 'false', 'false'
);
INSERT IGNORE INTO `config` (`name`,`value`,`title`, `description`, `field_id`, `options`, `type_id`, `editable`, `hidden`, `required`)
VALUES (
	  'push_apns_cert_key', '', 'Apple Push Notification Service Certificate Key', '',
	  (SELECT cf.id FROM lu_config_field cf WHERE cf.field = 'input'), '',
	  (SELECT ct.id FROM lu_config_type ct WHERE ct.type LIKE 'modules'),
	  'true', 'false', 'false'
);

UPDATE `config` SET editable='true',hidden='false' WHERE name IN('push_apns_cert_key','push_apns_cert_path','push_gcm_api_key');
