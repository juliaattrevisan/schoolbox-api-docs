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
  'scrub_html_cache_timeout',
  60*60*24*7, -- 1 week
  'Scrubbed HTML cache timeout',
  'Timeout for cache of scrubbed HTML values',
  (SELECT `id` FROM `lu_config_field` WHERE `field` = 'input' LIMIT 1),
  '',
  (SELECT `id` FROM `lu_config_type` WHERE `type` = 'Resources' LIMIT 1),
  'false',
  'true',
  'false'
);
