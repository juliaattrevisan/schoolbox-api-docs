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
  'external_document_docman_categories',
  'Academic Report DocMan Categories To Show',
  'Reports',
  'Comma separated list of categories used in Synergetic DocMan to categorise documents as academic reports. If blank, all DocMan documents will be included.',
  1,
  (SELECT `id` FROM lu_config_type WHERE type = 'Document Management'),
  'true',
  'false'
);