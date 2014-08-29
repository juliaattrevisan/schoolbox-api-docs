INSERT INTO `config` (`name`, `value`, `title`, `description`, `field_id`, `type_id`, `editable`) VALUES (
	'saml_debug',
	'0',
	'Enable Single Sign-On Debug Logging',
	'Enables debug logging for Single Sign On via SAML',
	'3',
	(SELECT `id` FROM lu_config_type WHERE `type` like 'Security'),
	'true'
);
