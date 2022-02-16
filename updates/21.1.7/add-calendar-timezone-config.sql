# Create a hole for calendar timezone
UPDATE config c
    INNER JOIN lu_config_type lct on c.type_id = lct.id
SET c.sequence = c.sequence + 1
WHERE lct.type = 'Localisation'
  AND c.sequence > (SELECT c2.sequence FROM config c2 WHERE c2.name = 'date_timezone' LIMIT 1);

INSERT INTO config (name, value, title, description, field_id, options, type_id, editable, hidden, required, module,
                    sequence)
VALUES ('calendar_view_timezone',
        '',
        'Calendar view timezone',
        'If set to local, calendar date time will display in browser\'s default timezone',
        (SELECT id FROM lu_config_field WHERE field = 'selectKey'),
        ';System:local;Local',
        (SELECT id FROM lu_config_type WHERE type = 'Localisation'),
        'true',
        'false',
        'false',
        'settings',
        (SELECT c.sequence + 1 FROM config c WHERE c.name = 'date_timezone' LIMIT 1))
