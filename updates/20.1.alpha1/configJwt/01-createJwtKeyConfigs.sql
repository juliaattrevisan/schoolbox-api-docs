# JWT private and public keys.
# Not sure if config is the appropriate place for these.
INSERT INTO config(
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
    'jwt_public_key',
    '',
    'JWT Public Key',
    'The public key used for verifying the signature of a JWT. Do not modify: this is auto-generated',
    (SELECT id FROM lu_config_field WHERE field = 'textarea'),
    '',
    (SELECT id FROM lu_config_type WHERE type = 'Security'),
    'false',
    'true',
    'true'
), (
    'jwt_private_key',
    '',
    'JWT Private Key',
    'The private key used for generating a signature for a JWT. Do not modify: this is auto-generated',
    (SELECT id FROM lu_config_field WHERE field = 'textarea'),
    '',
    (SELECT id FROM lu_config_type WHERE type = 'Security'),
    'false',
    'true',
    'true'
);
