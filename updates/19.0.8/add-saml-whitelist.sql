-- Add a whitelist of IPs to force into SAML
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
  'saml_ip_whitelist',
  '',
  'SAML IP Whitelist',
  'A comma seperated lists of CIDR IP ranges that if matched will force SAML auto-login.  This can be used to force internal clients to use SAML, leaving external clients to select what login system they prefer',
  (SELECT id FROM lu_config_field WHERE field = 'input'),
  '',
  (SELECT id FROM lu_config_type WHERE type = 'SAML'),
  'true',
  'true',
  'false'
);
