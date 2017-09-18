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
  'google_analytics_opt_in',
  'Allow Schoolbox to track anonymous usage data',
  0,
  'If turned on, Schoolbox will be able to see how you navigate your instance, using Google Analytics. This will help the team make product improvements for future use. All data will remain anonymous.',
  3,
  (SELECT `id` FROM lu_config_type WHERE `type` = 'Site'),
  true,
  true
);