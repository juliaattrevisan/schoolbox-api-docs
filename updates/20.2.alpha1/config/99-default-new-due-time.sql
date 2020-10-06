INSERT INTO config(name, value, title, description, field_id, options, type_id, editable, hidden, required, module, sequence)
VALUES (
  'lms_duetime_default',
  null,
  'Default Time Activities are Due',
  'Set the time using a 24 hour format (hh:mm).',
  (SELECT id FROM lu_config_field WHERE field = 'input'),
  '',
  (SELECT id FROM lu_config_type WHERE `type` = 'LMS Policy'),
  'true',
  'false',
  'false',
  'settings',
  (SELECT MAX(sequence) + 1 FROM config c WHERE `module` = 'settings' AND type_id = (SELECT id FROM lu_config_type WHERE `type` = 'LMS Policy'))
);
