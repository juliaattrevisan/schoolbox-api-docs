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
  'webdav_allow_any_auth',
  '0',
  'Enable WebDAV any auth type',
  'If this is enabled, then Schoolbox will poll the WebDAV server for the best authentication method - otherwise, only basic authentication will be attempted.',
  'boolean',
  NULL,
  'Resources',
  'false',
  'true',
  'true'
);