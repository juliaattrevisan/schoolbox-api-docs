-- Add a blacklist of IPs that will prevent SAML working if the user has this IP
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
  'saml_ip_blacklist',
  '',
  'SAML IP Blacklist',
  'A comma seperated lists of IP addresses that if matched will prevent SAML from working',
  (SELECT id FROM lu_config_field WHERE field = 'input'),
  '',
  (SELECT id FROM lu_config_type WHERE type = 'SAML'),
  'true',
  'true',
  'false'
);
