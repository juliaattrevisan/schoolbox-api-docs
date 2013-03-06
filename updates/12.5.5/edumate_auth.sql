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
  'edumate_auth_enabled',
  '0',
  'Enable Edumate Authentication',
  'Enable authentication via Edumate system. If this fails, authentication will fall back to local.',
  'boolean',
  '',
  'Security',
  'true',
  'false',
  'false'
),
(
  'edumate_auth_url',
  '',
  'Edumate Authentication URL',
  'The URL to send auth requests to if Edumate authentication is being used.',
  'input',
  '',
  'Security',
  'true',
  'false',
  'false'
);
