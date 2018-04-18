INSERT INTO config (name, title, description, value, field_id,  type_id, editable, hidden, required)
VALUES (
  'site_header_scripts',
  'Custom JavaScript',
  'Use this field to inject any custom JavaScript into your instances'' &lt;head&gt; section. This will run on every page except for admin pages. Please be sure to include &lt;script&gt; tags.',
  '',
  (SELECT id FROM lu_config_field WHERE field = 'textarea'),
  (SELECT id FROM lu_config_type WHERE type = 'Site'),
  'true',
  'false',
  'false'
);
