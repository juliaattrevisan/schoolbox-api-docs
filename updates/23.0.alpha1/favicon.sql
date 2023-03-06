INSERT INTO config (
                    name, value, title, description, field_id, type_id, editable, hidden, required, module, sequence
                    )
VALUES (
        'skin_favicon',
        null,
        'App Icon',
        'This image will be used as the favicon  shown in browser tabs, bookmarks and when the app is installed on a device. Please provide a square 192px PNG image.',
        (select id from lu_config_field where field = 'file'),
        (select id from lu_config_type where type = 'Skin Config'),
        'true',
        'false',
        'true',
        'settings',
        (SELECT max(sequence) + 1
         FROM config c
         WHERE c.type_id = (select id from lu_config_type where type = 'Skin Config') LIMIT 1));
