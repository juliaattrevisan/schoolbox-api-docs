INSERT INTO config (
                    name, value, title, description, field_id, type_id, editable, hidden, required, module, sequence
                    )
VALUES (
        'skin_favicon',
        null,
        'App Icon',
        'Upload a square image of at least 192x192 pixels, that will be used as the favicon shown in browser tabs, bookmarks and when the app is installed on a device.',
        (select id from lu_config_field where field = 'file'),
        (select id from lu_config_type where type = 'Skin Config'),
        'true',
        'false',
        'false',
        'settings',
        (SELECT max(sequence) + 1
         FROM config c
         WHERE c.type_id = (select id from lu_config_type where type = 'Skin Config') LIMIT 1));
