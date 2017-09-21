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
  'site_usage_tracking',
  'Allow Schoolbox to track anonymous usage data',
  0,
  'If turned on, Schoolbox will be able to see how you navigate your instance, using Google Analytics. This will help the team make product improvements for future use, and all data will remain anonymous. Please check the privacy policy on our website, located under the \'Legal\' tab in our footer, for more information.',
  3,
  (SELECT `id` FROM lu_config_type WHERE `type` = 'Site'),
  true,
  true
);