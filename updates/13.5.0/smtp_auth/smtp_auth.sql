
INSERT INTO `config`(
  `name`,
  `value`,
  `title`,
  `description`,
  `field`,
  `options`,
  `type`,
  `editable`,
  `hidden`,
  `required`
)
VALUES (
  'mail_smtp_username',
  '',
  'SMTP Username',
  'If this is set, SMTP Auth will be used with this username and corresponding password',
  'input',
  NULL,
  'Email',
  'true',
  'false',
  'false'
), (
  'mail_smtp_password',
  '',
  'SMTP Password',
  'Password for SMTP Auth, only used if username is set',
  'input',
  NULL,
  'Email',
  'true',
  'false',
  'false'
);