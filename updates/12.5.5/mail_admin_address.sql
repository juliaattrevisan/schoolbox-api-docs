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
  'mail_admin_address',
  '',
  'Administrator Email Address',
  'Emails for the Schoolbox Administrator (you, if you''re changing this setting) will be sent to this address.',
  'input',
  '',
  'Email',
  'true',
  'false',
  'false'
);
