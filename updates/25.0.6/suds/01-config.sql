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
    required,
    module,
    sequence
) VALUES (
    'guzzle_custom_user_agent',
    'schoolbox',
    'Custom Guzzle User Agent',
    'Cloudflare can whitelist this user agent for access to Cloudflare-protected urls.',
    (SELECT id FROM lu_config_field WHERE field = 'input'),
    '',
    (SELECT id FROM lu_config_type WHERE type = '3rd Party Services'),
    'false',
    'true',
    'true',
    'settings',
    (SELECT MAX(sequence)+1 FROM `config` c)
);
