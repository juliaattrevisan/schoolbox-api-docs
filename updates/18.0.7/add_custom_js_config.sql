INSERT INTO config (name, title, description, value, field_id,  type_id, editable, hidden, required)
VALUES (
  'site_header_scripts',
  'Custom Java Scripts',
  'Provide a custom script which runs before pages load, excluding admin pages. Make sure to surround it with the script tag.',
  '',
  (SELECT id FROM lu_config_field WHERE field = 'textarea'),
  (SELECT id FROM lu_config_type WHERE type = 'Site'),
  'true',
  'false',
  'false'
);
