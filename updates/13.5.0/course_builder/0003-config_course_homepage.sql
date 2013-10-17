-- Config option for the course base homepage.
-- Currently assumes that config options have been converted to lookups.
INSERT IGNORE INTO `lu_config_field` (`id`, `field`) VALUES (9, 'resource');
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
  'course_homepage',
  '',
  'Course Base Homepage',
  'This homepage will be used as the base for the course selector.',
  (SELECT `id` FROM `lu_config_field` WHERE `field` = 'resource' LIMIT 1),
  '',
  (SELECT `id` FROM `lu_config_type` WHERE `type` = 'Resources' LIMIT 1),
  'true',
  'false',
  'false'
);
