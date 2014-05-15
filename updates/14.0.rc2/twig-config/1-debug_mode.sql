-- Template Debug mode - off for everyone except demo by default.
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
  'debug_templates',
  (IF ((SELECT COUNT(*) FROM `config` `c` WHERE `c`.`name` = 'site_location' AND `c`.`value` = 'demo'), '1', '0')),
  'Debug Templates',
  'If this is on, Twig templates will be loaded in debug mode. This may be slower, but changes to templates are guaranteed to be recognised immediately.',
  3, -- boolean
  '',
  (SELECT `id` FROM lu_config_type WHERE `type` like 'Debug'),
  'false',
  'true',
  'false'
);
