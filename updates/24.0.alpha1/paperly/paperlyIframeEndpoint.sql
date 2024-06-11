INSERT INTO config (name, value, title, description, field_id, options, type_id, editable, hidden, required,
                    module, sequence)

VALUES
    (
         'paperly_iframe_api_host',
         NULL,
         'Paperly API Host',
         'The Paperly host to access the API.',
         (SELECT id FROM lu_config_field WHERE `field` = 'input'),
         '',
         (SELECT id FROM lu_config_type WHERE `type` = '3rd Party Services'),
         'true',
         'false',
         'false',
         'settings',
         (
             SELECT MAX(sequence)+1
             FROM config c
             WHERE `module` = 'settings'
         )
    ),
    (
        'paperly_iframe_api_key',
        NULL,
        'Paperly API Key',
        'The API key for accessing the Paperly API.',
        (SELECT id FROM lu_config_field WHERE `field` = 'input'),
        '',
        (SELECT id FROM lu_config_type WHERE `type` = '3rd Party Services'),
        'true',
        'false',
        'false',
        'settings',
        (
            SELECT MAX(sequence)+1
            FROM config c
            WHERE `module` = 'settings'
        )

    )
