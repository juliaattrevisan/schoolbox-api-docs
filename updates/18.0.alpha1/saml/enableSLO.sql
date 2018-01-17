-- Add a feature flag to enable SLO support in Schoolbox
INSERT INTO config (
  name,
  value,
  title,
  description,
  field_id,
  options,
  type_id,
  editable,
  hidden,
  required
) VALUES (
  'saml_slo_enabled',
  '1',
  'Enable SAML SLO',
  'If enabled, logout performed in Schoolbox will be sent to the IdP to log the user out of all other services registered with the IdP.',
  (SELECT id FROM lu_config_field WHERE field = 'boolean'),
  '',
  (SELECT id FROM lu_config_type WHERE type = 'Security'),
  'true',
  'false',
  'true'
);

-- Add a feature flag to enable SLO support in Schoolbox
INSERT INTO config (
  name,
  value,
  title,
  description,
  field_id,
  options,
  type_id,
  editable,
  hidden,
  required
) VALUES (
  'saml_slo_url',
  '1',
  'SAML SLO URL Endpoint',
  'If your IdP metadata is not provided or does not contain an SLO endpoint, configure here where SLO requests should go',
  (SELECT id FROM lu_config_field WHERE field = 'input'),
  '',
  (SELECT id FROM lu_config_type WHERE type = 'Security'),
  'true',
  'false',
  'false'
);

-- Add a feature flag to enable SLO support in Schoolbox
INSERT INTO config (
  name,
  value,
  title,
  description,
  field_id,
  options,
  type_id,
  editable,
  hidden,
  required
) VALUES (
  'saml_public_cert',
  '1',
  'Schoolbox SAML SP x509 Certificate',
  'This contains the Schoolbox SAML public certificate, this is provided to the SAML IdP for validating our requests. It is automatically generated per school',
  (SELECT id FROM lu_config_field WHERE field = 'bytes'),
  '',
  (SELECT id FROM lu_config_type WHERE type = 'Security'),
  'false',
  'true',
  'false'
);

-- Add a feature flag to enable SLO support in Schoolbox
INSERT INTO config (
  name,
  value,
  title,
  description,
  field_id,
  options,
  type_id,
  editable,
  hidden,
  required
) VALUES (
  'saml_private_cert',
  '1',
  'Schoolbox SAML SP Private Key',
  'This key is used to sign requests sent to the IdP from Schoolbox, it is automatically generated per school',
  (SELECT id FROM lu_config_field WHERE field = 'bytes'),
  '',
  (SELECT id FROM lu_config_type WHERE type = 'Security'),
  'false',
  'true',
  'false'
);
