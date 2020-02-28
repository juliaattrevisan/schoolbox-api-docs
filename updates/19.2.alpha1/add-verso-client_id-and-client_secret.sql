-- Add the client_id and client_secret credentials for Verso Gateway API.
INSERT INTO config (name, value, title, description, field_id, options, type_id, editable, hidden, required)
VALUES ('verso_client_id',
        null,
        'Verso Client Id',
        'Verso Client Id is used for OAuth2 authentication to get student travel log from Verso.',
        (SELECT id
         FROM `lu_config_field`
         WHERE field = 'input'),
        NULL,
        (SELECT id FROM lu_config_type WHERE type = '3rd Party Services'),
        'false',
        'true',
        'true'),
       ('verso_client_secret',
        null,
        'Verso Client Secret',
        'Verso Client Secret is used for OAuth2 authentication to get student travel log from Verso.',
        (SELECT id FROM lu_config_field WHERE field = 'input'),
        NULL,
        (SELECT id
         FROM `lu_config_type`
         WHERE type = '3rd Party Services'),
        'false',
        'true',
        'true');
