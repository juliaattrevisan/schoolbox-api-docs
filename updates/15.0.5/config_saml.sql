/* Extra SAML Config options - SB-8404 */
INSERT INTO `config` (
  `name`, `value`, `title`, `description`, `field_id`, `options`, `type_id`, `editable`, `hidden`, `required`
) VALUES  (
	'saml_auth_comparison',
	'exact',
	'Single Sign-On Authentication Type Comparison',
	'Allow the SAML identity provider to choose the authentication type (Web Form, WIA/Kerberos) based on the browsers support, or not (Default: exact)',
	(SELECT `id` FROM lu_config_field WHERE `field` like 'select'),
	'exact:minimum:maximum:better',
	(SELECT `id` FROM lu_config_type WHERE `type` like 'Security'),
	'true',
	'false',
	'false'
)
ON DUPLICATE KEY UPDATE `configID` = `configID`;

INSERT INTO `config` (
  `name`, `value`, `title`, `description`, `field_id`, `options`, `type_id`, `editable`, `hidden`, `required`
) VALUES  (
	'saml_krb_chrome',
	'1',
	'Single Sign-On Kerberos Chrome',
	'Enable Chrome browser on desktop for using kerberos authentication with SAML.',
	(SELECT `id` FROM lu_config_field WHERE `field` like 'boolean'),
	'',
	(SELECT `id` FROM lu_config_type WHERE `type` like 'Security'),
	'true',
	'false',
	'false'
)
ON DUPLICATE KEY UPDATE `configID` = `configID`;
