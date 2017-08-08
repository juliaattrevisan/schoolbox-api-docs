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
  'external_document_report_enabled',
  'Display Academic Reports From SIS On Student Profile',
  0,
  'Enable the display of documents from External SIS document management feature on the students profile page (Synergetic Only)',
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
  'external_document_report_minimum_date',
  'Only Show Academic Reports On Or After This Date',
  '',
  'Only list academic reports on or after this date (please use YYYY-MM-DD format, eg "2017-01-30").If blank all reports will be shown.',
  1,
  (SELECT `id` FROM lu_config_type WHERE type = 'Document Management'),
  'true',
  'false'
);

