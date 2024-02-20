INSERT INTO config (
    name,
    value,
    title,
    description,
    field_id,
    type_id,
    editable,
    hidden,
    required,
    module
) VALUES (
    'firebase_private_key',
    '',
    'Firebase Private Key',
    'Private key for Firebase''s services',
    (SELECT id FROM lu_config_field WHERE `field` = 'textarea'),
    (SELECT id FROM lu_config_type  WHERE `type`  = '3rd Party Services'),
    'false',
    'true',
    'false',
    'settings'
);
