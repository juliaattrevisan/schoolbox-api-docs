/* SB-1656 */
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
  'restrict_parent_emails',
  IF ((SELECT `c2`.`value` FROM `config` AS `c2` WHERE `c2`.`name` = 'site_location') IN ('wenona','bialik','balcombe'), '1', '0'),
  'Restrict Display of Parent Emails',
  'If ticked, restricts the display of parent email addresses to staff only.',
  'boolean',
  'true:false',
  'Policy',
  'true',
  'false',
  'false'
);
