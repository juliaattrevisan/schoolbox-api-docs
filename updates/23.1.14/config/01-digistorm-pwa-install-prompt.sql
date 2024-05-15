INSERT INTO config (name, value, title, description, field_id, options, type_id, editable, hidden, required,
                    module, sequence)
VALUES
    (
        'digistorm_apple_app_store_link',
        NULL,
        'Digistorm Apple App Store Link',
        'You can insert the Digistorm Apple App Store URL and include HTTP or HTTPS to it.',
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
        'digistorm_google_play_store_link',
        NULL,
        'Digistorm Google Play Store Link',
        'You can insert the Digistorm Google Play Store URL and include HTTP or HTTPS to it.',
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
        'digistorm_role_install_prompt',
        'Staff,Parent,Student',
        'Digistorm Role Install Prompt',
        'This will allow you to select one or more user roles from the list to redirect the user to install the Digistorm app. If you don''t select a specific role, it will prompt you for all roles during the installation.',
        (SELECT id FROM lu_config_field WHERE `field` = 'multiSelect'),
        'Staff:Parent:Student',
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
    );
