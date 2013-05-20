INSERT INTO `config`(
  `name`,
  `value`,
  `title`,
  `description`,
  `field`,
  `options`,
  `type`,
  `editable`,
  `hidden`,
  `required`
)
VALUES (
  'digest_send_daily',
  IF ((SELECT `c2`.`value` FROM `config` AS `c2` WHERE `c2`.`name` = 'site_location') IN ('jpc'), '1', '0'),
  'Daily digest enabled',
  'Send daily digest of news, upcoming work and recent activity. If enabled, it is by default configured to send Monday to Thursday at 6am.',
  'boolean',
  NULL,
  'Email',
  'true',
  'false',
  'false'
), (
  'digest_send_weekly',
  IF ((SELECT `c2`.`value` FROM `config` AS `c2` WHERE `c2`.`name` = 'site_location') IN ('jpc'), '1', '0'),
  'Weekly digest enabled',
  'Send weekly digest of news, upcoming work and recent activity. If enabled, it is by default configured to send Friday at 11am.',
  'boolean',
  NULL,
  'Email',
  'true',
  'false',
  'false'
);