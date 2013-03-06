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
  'mail_admin_address',
  -- For Shore: itmanager@shore.nsw.edu.au
  IF ((SELECT `c2`.`value` FROM `config` AS `c2` WHERE `c2`.`name` = 'site_location') = 'shore', 'itmanager@shore.nsw.edu.au', ''),
  'Administrator Email Address',
  'Emails for the Schoolbox Administrator (you, if you''re changing this setting) will be sent to this address.',
  'input',
  '',
  'Email',
  'true',
  'false',
  'false'
);
