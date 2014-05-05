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
    'lms_student_policy',
    '',
    'Student Submit Terms',
    'If set, students will be asked to agree to these terms when submitting work',
    8,
    null,
    (SELECT `id` FROM lu_config_type WHERE `type` like 'LMS Policy'),
    'true',
    'false',
    'false'
  );