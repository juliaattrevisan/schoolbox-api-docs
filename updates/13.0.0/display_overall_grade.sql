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
  'show_overall_grade',
  IF ((SELECT `c2`.`value` FROM `config` AS `c2` WHERE `c2`.`name` = 'site_location') = 'bialik', '0', '1'),
  'Show overall grade on Due Work page?',
  'Is the overall grade displayed on the Due Work page for students? If your assessments aren''t evenly weighted, you may want to turn this off.',
  'boolean',
  '',
  'Policy',
  'true',
  'false',
  'false'
);
