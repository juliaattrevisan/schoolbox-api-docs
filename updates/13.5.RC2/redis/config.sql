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
  'redis_url',
  'tcp://127.0.0.1:6379',
  'Redis URL',
  'URL for Redis server',
  (SELECT `id` FROM `lu_config_field` WHERE `field` = 'input' LIMIT 1),
  '',
  (SELECT `id` FROM `lu_config_type` WHERE `type` = 'Resources' LIMIT 1),
  'false',
  'true',
  'false'
);
