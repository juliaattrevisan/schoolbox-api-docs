
INSERT INTO `config`(
  `name`,
  `value`,
  `title`,
  `description`,
  `field_id`,
  `options`,
  `type_id`,
  `editable`,
  `hidden`,
  `required`
)
VALUES (
  'mail_smtp_username',
  '',
  'SMTP Username',
  'If this is set, SMTP Auth will be used with this username and corresponding password',
  1, -- 'input',
  NULL,
  8, -- 'Email',
  'true',
  'false',
  'false'
), (
  'mail_smtp_password',
  '',
  'SMTP Password',
  'Password for SMTP Auth, only used if username is set',
  1, -- 'input',
  NULL,
  8, -- 'Email',
  'true',
  'false',
  'false'
);