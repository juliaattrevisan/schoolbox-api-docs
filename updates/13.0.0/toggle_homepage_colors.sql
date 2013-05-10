INSERT INTO `config` (
  `name`,
  `value`,
  `title`,
  `description`,
  `field`,
  `type`,
  `editable`,
  `required`
)
VALUES (
  'homepage_enable_colour',
  IF ((SELECT `c2`.`value` FROM `config` AS `c2` WHERE `c2`.`name` = 'site_location') IN ('balcombe', 'seymour'), '0', '1'),
  'Enable homepage colours?',
  'Is the colour selector enabled on homepages?',
  'boolean',
  'Colours',
  'true',
  'true'
);
