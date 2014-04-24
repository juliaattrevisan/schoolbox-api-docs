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
    'submission_visibility_max',
    'Staff, Students and their Parents',
    'Maximum Submission Grade Visibility',
    'This will control what the maximum grade visibility teachers can use when creating submission boxes',
    7,
    '0;Staff Only:1;Staff and Students:2;Staff, Students and their Parents',
    (SELECT `id` FROM lu_config_type WHERE `type` like 'LMS Policy'),
    'true',
    'false',
    'false'
  );