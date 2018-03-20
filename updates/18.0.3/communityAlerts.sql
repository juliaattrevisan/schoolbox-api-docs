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
  'Controls if student alerts are displayed on the students profile.  These alerts are only shown to teachers when enabled.',
  3,
  (SELECT `id` FROM lu_config_type WHERE `type` = 'Policy'),
  true,
  true
);
