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
    'paperly_agent_ip_whitelist',
    NULL,
    'Paperly Agent IP Address Whitelist',
    'A comma-separated list of IP addresses which are allowed to access the Paperly agent.  Note that the agent currently supports Synergetic only.',
    (SELECT id FROM lu_config_field WHERE `field` = 'input'),
    '',
    (SELECT id FROM lu_config_type WHERE `type` = '3rd Party Services'),
    'true',
    'false',
    'false',
    'settings',
    (SELECT MAX(sequence) + 1 FROM config c WHERE `module` = 'settings')
), (
    'paperly_agent_secret',
    NULL,
    'Paperly Agent Secret',
    'The shared secret between this instance and Paperly.',
    (SELECT id FROM lu_config_field WHERE `field` = 'password'),
    '',
    (SELECT id FROM lu_config_type WHERE `type` = '3rd Party Services'),
    'true',
    'false',
    'false',
    'settings',
    (SELECT MAX(sequence) + 1 FROM config c WHERE `module` = 'settings')
);

