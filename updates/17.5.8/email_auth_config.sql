INSERT INTO `config` (
  `name`,
  `title`,
  `value`,
  `description`,
  `field_id`,
  `type_id`,
  `editable`,
  `hidden`,
  `required`
) VALUES (
  'auth_email_enabled',
  'Allow users to login with email address',
  0,
  'This will allow users to login to with either a username or alternative email address.  Please note if email address is not unique this may cause unexpected results',
  5,
  (SELECT `id` FROM lu_config_type WHERE type = 'Security'),
  'true',
  'false',
  'true'
);