INSERT INTO `config` (
  `name`,
  `title`,
  `value`,
  `description`,
  `field_id`,
  `type_id`,
  `editable`,
  `required`
) VALUES (
  'forgotten_password_title',
  'Forgotten Password Page Title',
  'Have You Forgotten Your Password?',
  'The title that will appear at the top of the forgotten password page.',
  1,
  (SELECT `id` FROM lu_config_type WHERE `type` = 'Policy'),
  true,
  true
);