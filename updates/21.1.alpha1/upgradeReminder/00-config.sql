-- SA-12246
-- insert new config items needed for the instance registry and upgrade reminder alerts

INSERT INTO config(name, value, title, description, field_id, options, type_id, editable, hidden, required, module, sequence)
VALUES ('dismiss_version',
        null,
        'Allow admin to dismiss an upgrade reminder',
        'The major version that should be prevented from showing on the administration page',
        (SELECT id FROM lu_config_field WHERE field = 'number'),
        '',
        (SELECT id FROM lu_config_type WHERE `type` = 'Policy'),
        'false',
        'true',
        'false',
        'settings',
        null);

INSERT INTO config(name, value, title, description, field_id, options, type_id, editable, hidden, required, module, sequence)
VALUES ('instance_registry_url',
        'https://manage.schoolbox.cloud',
        'Allow admin to dismiss an upgrade reminder',
        'The major version that should be prevented from showing on the administration page',
        (SELECT id FROM lu_config_field WHERE field = 'input'),
        '',
        null,
        'false',
        'true',
        'false',
        'settings',
        null);

INSERT INTO config(name, value, title, description, field_id, options, type_id, editable, hidden, required, module, sequence)
VALUES ('instance_registry_api_caching',
        '1800',
        'Instance Registry Update Frequency',
        'How long instance registry data should be cached for before being re-queried',
        (SELECT id FROM lu_config_field WHERE field = 'input'),
        '',
        null,
        'false',
        'true',
        'false',
        'settings',
        null);

INSERT INTO config(name, value, title, description, field_id, options, type_id, editable, hidden, required, module, sequence)
VALUES ('site_environment',
        'client-hosted',
        'What type of environment is this hosted in',
        'Is this client, cloud or another kind of hosting environment',
        (SELECT id FROM lu_config_field WHERE field = 'input'),
        '',
        null,
        'false',
        'true',
        'false',
        'settings',
        null);

