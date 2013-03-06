-- Config setting for external DB's character encoding.
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
  'external_db_encoding',
  'ISO-8859-1',
  'Database Encoding',
  'The encoding that the external database uses to store text fields. Most SQL Server databases will use ISO-8859-1.',
  'input',
  '',
  'External DB',
  'true',
  'false',
  'false'
);
