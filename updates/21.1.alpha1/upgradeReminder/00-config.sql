-- SA-12246
-- insert new config items needed for the instance registry and upgrade reminder alerts

INSERT INTO config(name, value, title, description, field_id, options, type_id, editable, hidden, required, module, sequence)
VALUES ('dismiss_version',
        null,
        'Dismissed Major Version',
        'The most recent major version that an admin has been reminded of, and has dismissed: they will not be reminded of this version again',
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
        'Instance Registry URL',
        'The URL of the Schoolbox instance registry: the most recent version is retrieved from here ',
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
        'Is this instance client-hosted or in the cloud SaaS environment',
        (SELECT id FROM lu_config_field WHERE field = 'select'),
        'client-hosted:cloud-sass',
        (SELECT id FROM lu_config_type WHERE `type` = 'Site'),
        'false',
        'true',
        'true',
        'settings',
        null);

