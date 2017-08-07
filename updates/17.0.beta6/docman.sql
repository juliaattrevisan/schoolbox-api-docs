INSERT INTO `lu_config_type` (`type`) VALUES ('Document Management');

INSERT INTO `config` (
  `name`,
  `title`,
  `value`,
  `description`,
  `field_id`,
  `type_id`,
  `editable`,
  `required`
) VALUES (
  'external_docman_enabled',
  'Enable Document Display On Student Profile',
  0,
  'Enable the display of documents from External SIS document management feature on the students profile page',
  5,
  (SELECT `id` FROM lu_config_type WHERE type = 'Document Management'),
  'true',
  'true'
);

INSERT INTO `config` (
  `name`,
  `title`,
  `value`,
  `description`,
  `field_id`,
  `type_id`,
  `editable`,
  `required`
) VALUES (
  'external_docman_filter',
  'Document Category Filter',
  '',
  'Comma separated list of categories to be displayed on the students profile page (Leave blank to display all documents)',
  1,
  (SELECT `id` FROM lu_config_type WHERE type = 'Document Management'),
  'true',
  'false'
);
