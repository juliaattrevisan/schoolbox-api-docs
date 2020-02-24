-- Add the client_id and client_secret credentials for Verso Gateway API.
INSERT INTO config (name, value, title, description, field_id, options, type_id, editable, hidden, required)
VALUES ('verso_client_id',
        '7m7f25307atgmqi9umjrs7401c',
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
        'uektuae65h0j9mvoj9sbekpntpdforpo6hobh33rcvuot3430l4',
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
