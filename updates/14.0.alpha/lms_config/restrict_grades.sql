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
    'restrict_parent_grades',
    0,
    'Hide Grades from Parents',
    'This will prevent parents from seeing grades in due work and from seeing the grade card completely',
    3,
    'true:false',
    (SELECT `id` FROM lu_config_type WHERE `type` like 'LMS Policy'),
    'true',
    'false',
    'false'
  );