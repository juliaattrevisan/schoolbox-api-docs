/* SB-2025 */
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
  'email_all_parents',
  IF ((SELECT `c2`.`value` FROM `config` AS `c2` WHERE `c2`.`name` = 'site_location') IN ('bialik','stk'), '0', '1'),
  'Allow Email All Parents',
  'If ticked, allows staff to email all the parents of a class with a single button',
  'boolean',
  'true:false',
  'Policy',
  'true',
  'false',
  'false'
);
