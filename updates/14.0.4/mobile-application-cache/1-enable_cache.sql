-- Mobile cache - off by default, causes too many problems otherwise
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
  'mobile_application_cache_enabled',
  0,
  'Enable Mobile Module Application Cache',
  'If this is on, the mobile application cache will be enabled. It is disabled by default, because many users have reported issues with it.',
  3, -- boolean
  '',
  (SELECT `id` FROM lu_config_type WHERE `type` like 'Modules'),
  'false',
  'true',
  'false'
);
