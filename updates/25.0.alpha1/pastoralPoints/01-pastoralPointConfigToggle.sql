INSERT INTO config (name, value, title, description, field_id, options, type_id, editable, hidden, required,
                    module, sequence)
VALUES (
           'pastoral_point_enabled',
           1,
           'Enable Pastoral Points',
           'If toggled to \'ON\', points can be assigned to pastoral records.',
           (SELECT id FROM lu_config_field WHERE `field` = 'boolean'),
           '',
           (SELECT id FROM lu_config_type WHERE `type` = 'Pastoral Care'),
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
