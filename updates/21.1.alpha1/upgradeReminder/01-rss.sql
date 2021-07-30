INSERT INTO config(name, value, title, description, field_id, options, type_id, editable, hidden, required, module, sequence)
VALUES ('release_notes_url',
        'https://help.schoolbox.com.au/news/feed/ab1cf283f09e5e61a1712b116?topic=release-notes',
        'RSS Feed',
        '',
        (SELECT id FROM lu_config_field WHERE field = 'input'),
        '',
        (SELECT id FROM lu_config_type WHERE `type` = 'Site'),
        'false',
        'true',
        'false',
        'settings',
        null);
