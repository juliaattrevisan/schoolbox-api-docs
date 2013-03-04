/* SB-2023 */
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
  'protect_medicalspecial',
  '0',
  'Hide Medical and Special Needs on Class Lists',
  'Tick this box to hide all Medical and Special Needs information from displaying on the Class List pages',
  'boolean',
  'true:false',
  'Policy',
  'true',
  'false',
  'false'
);
