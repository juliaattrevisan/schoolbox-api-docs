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
    required,
    module,
    sequence
)
VALUES (
    'jwt_leeway',
    60,
    'JWT Leeway',
    'The amount of seconds in leeway permissible when verifying a JWT\'s "iat" (issued at), "nbf" (not before) and "exp" (expiration) claims.',
    (SELECT id FROM lu_config_field WHERE field = 'number'),
    '',
    (SELECT id FROM lu_config_type WHERE `type` = 'Security'),
    'false',
    'true',
    'true',
    'settings',
    null
);
