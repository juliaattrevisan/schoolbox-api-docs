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
        'Staff,Parents,Students',
        'Digistorm Role Install Prompt',
        'Selecting one or more roles to redirect to the user to install that Digistorm app. If none are selected, will prompt for all',
        (SELECT id FROM lu_config_field WHERE `field` = 'multiSelect'),
        'Staff:Parents:Students',
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
