-- A number of instances failed the 20.1.7 upgrade where we added the etherpad_host config.
-- We manually repaired most via Ansible, but we don't have SSH access to all, so need to fix any remaining instances.
-- We'll just set this to 'http://localhost:9001', cloud instances will overwrite this post-upgrade anyway.
INSERT IGNORE INTO config (
    name,
    value,
    title,
    description,
    field_id,
    type_id,
    editable,
    hidden,
    required,
    module,
    sequence
) VALUES (
    'etherpad_host',
    'http://localhost:9001',
    'Etherpad host',
    'Etherpad url including scheme and port',
    (SELECT id FROM lu_config_field WHERE field = 'input'),
    (SELECT id FROM lu_config_type WHERE type = '3rd Party Services'),
    'false',
    'true',
    'true',
    'settings',
    (SELECT * FROM (SELECT MAX(sequence) + 1 FROM config WHERE type_id = (SELECT id FROM lu_config_type WHERE type = '3rd Party Services')) t1)
);
