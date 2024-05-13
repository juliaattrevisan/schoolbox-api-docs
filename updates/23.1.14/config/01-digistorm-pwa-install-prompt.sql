INSERT INTO schoolbox.config (name, value, title, description, field_id, options, type_id, editable, hidden, required,
                              module, sequence)
VALUES
    ('digistorm_apple_app_store_link', NULL, 'Digistorm Apple App Store Link',
     'Insert the Digistorm Apple App Store URL. NOTE: You will need to include HTTP or HTTPS.', 1, '', 10, 'true',
     'false', 'false', 'settings', 94),

    ('digistorm_google_play_store_link', NULL, 'Digistorm Google Play Store Link',
     'Insert the Digistorm Google Play Store URL. NOTE: You will need to include HTTP or HTTPS.', 1, '', 10, 'true',
     'false', 'false', 'settings', 95),

    ('digistorm_role_install_prompt', '', 'Digistorm Role Install Prompt',
     'Selecting one or more roles to redirect to the user to install that Digistorm app. If none are selected, will prompt for all',
     17, 'Staff:Parents:Students', 10, 'true', 'false', 'false', 'settings', 96);
