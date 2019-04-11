INSERT INTO config (name, value, title, description, field_id, options, type_id, editable, hidden, required, module)
VALUES (
  'saml_auth_context',
  'password',
  'Requested Auth Context',
  '',
  4,
  'password:unspecified',
  (SELECT id from lu_config_type WHERE type = 'SAML'),
  'true',
  'false',
  'false',
  'settings'
);