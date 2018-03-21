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
  'synergetic_community_alerts',
  'Show SIS Alerts on Student Profile',
  '1',
  'Controls whether student alerts are displayed on students'' profiles. When enabled, these alerts are only shown to staff.',
  3,
  (SELECT `id` FROM lu_config_type WHERE `type` = 'Policy'),
  true,
  true
);
