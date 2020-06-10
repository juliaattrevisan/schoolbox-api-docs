INSERT INTO config (
    name,
    value,
    title,
    description,
    field_id,
    type_id,
    editable,
    hidden,
    required
) VALUES (
    'ldap_ca_certificate',
    '',
    'LDAP CA Certificate',
    'If you use a self-signed certificate authority for LDAP, please provide a Base64 encoded X.509 certificate exported from your certificate authority.',
    (SELECT id FROM lu_config_field WHERE field = 'file'),
    (SELECT id FROM lu_config_type WHERE type = 'LDAP'),
    'true',
    'false',
    'false'
);
