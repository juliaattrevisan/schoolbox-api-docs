INSERT INTO `config`(
  `name`,
  `value`,
  `title`,
  `description`,
  `field_id`,
  `options`,
  `type_id`,
  `editable`,
  `hidden`,
  `required`
)
VALUES (
  'external_learning_plans',
  '0',
  'Show Learning Plans',
  'If enabled an alert for synergetic will display learning plans available alert on the students profile screen',
  5, -- checkbox
  NULL,
  5, -- policy
  'true',
  'false',
  'false'
);