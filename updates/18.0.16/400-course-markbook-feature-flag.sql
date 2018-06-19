INSERT INTO config(name, value, title, description, field_id, options, type_id, editable, hidden, required)
VALUES (
  'course_markbook_enable',
  '0',
  'Enable Course Markbook Module',
  'If set, Course Markbook will be available',
  (SELECT id FROM lu_config_field WHERE field = 'boolean'),
  '',
  (SELECT id FROM lu_config_type WHERE type = 'Modules'),
  'true',
  'true',
  'false'
);
