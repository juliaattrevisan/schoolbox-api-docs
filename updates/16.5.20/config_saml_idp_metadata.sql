INSERT INTO `config` (
  `name`, `value`, `title`, `description`, `field_id`, `options`, `type_id`, `editable`, `hidden`, `required`
) VALUES  (
	'saml_idp_metadata_url',
	'',
	'Single Sign-On Identity Provider Metadata URL',
	'URL to the SAML IDP Metadata URL. E.g. https://adfs.school.edu/FederationMetadata/2007-06/FederationMetadata.xml',
	(SELECT `id` FROM lu_config_field WHERE `field` like 'input'),
	'',
	(SELECT `id` FROM lu_config_type WHERE `type` like 'Security'),
	'true',
	'false',
	'false'
)
ON DUPLICATE KEY UPDATE `configID` = `configID`;
