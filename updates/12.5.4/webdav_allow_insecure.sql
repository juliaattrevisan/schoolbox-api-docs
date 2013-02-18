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
  'webdav_allow_insecure',
  -- For now, only Seymour has an invalid host.
  IF ((SELECT `value` FROM `config` WHERE `name` = 'site_location') = 'seymour', '1', '0'),
  'Enable WebDAV insecure connections',
  'If WebDAV is enabled, this allows connections to be made if the WebDAV host''s SSL certificate cannot be verified. Please attempt to use a verified certificate before resorting to this option.',
  'boolean',
  NULL,
  'Resources',
  'false',
  'true',
  'true'
);