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
  'password_change_email_from',
  IF (
      (SELECT `c2`.`value` FROM `config` AS `c2` WHERE `c2`.`name` = 'site_location') = 'shore',
      'bursar@shore.nsw.edu.au',
      (SELECT `c2`.`value` FROM `config` AS `c2` WHERE `c2`.`name` = 'mail_from_address')
  ),
  'Password Change Email From Address',
  'The email address that users receive password change emails from.',
  'input',
  '',
  'Email',
  'true',
  'false',
  'false'
),
(
  'password_change_email_subject',
  IF (
      (SELECT `c2`.`value` FROM `config` AS `c2` WHERE `c2`.`name` = 'site_location') = 'shore',
      'Password change for the Shore School Portal Lampada',
      CONCAT((SELECT `c2`.`value` FROM `config` AS `c2` WHERE `c2`.`name` = 'title'), ' Password Change')
  ),
  'Password Change Email Subject',
  'The subject of the email that users receive when they change their password.',
  'input',
  '',
  'Email',
  'true',
  'false',
  'false'
),
(
  'password_change_failed_email_subject',
  IF (
      (SELECT `c2`.`value` FROM `config` AS `c2` WHERE `c2`.`name` = 'site_location') = 'shore',
      'Lampada password change attempt failure',
      CONCAT((SELECT `c2`.`value` FROM `config` AS `c2` WHERE `c2`.`name` = 'title'), ' Password Change Failed')
  ),
  'Password Change Faild Email Subject',
  'The subject of the email that is sent to the Schoolbox Administrator when someone fails to change their password.',
  'input',
  '',
  'Email',
  'true',
  'false',
  'false'
);
