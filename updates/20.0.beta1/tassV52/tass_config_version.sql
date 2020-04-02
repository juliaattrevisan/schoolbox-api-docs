INSERT INTO config (name, value, title, description, field_id, options, type_id, editable, hidden, required, sequence)
VALUES (
  'external_tass_version',
  '',
  'Tass version',
  'Set Tass version',
  (SELECT id FROM lu_config_field WHERE field = 'selectKey'),
  'V52;V52',
  (SELECT id FROM lu_config_type WHERE type = 'External DB'),
  'true',
  'false',
  'false',
  42
);
