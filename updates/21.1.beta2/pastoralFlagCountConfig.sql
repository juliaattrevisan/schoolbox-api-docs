INSERT INTO `config`
(name, value, title, description, field_id, options, type_id, editable, hidden, required, module, sequence)
VALUES ('pastoral_flag_count_period',
        1,
        'Pastoral Flag Count Period',
        'This is the period of time that the pastoral records will be counted in the pastoral flags. Beyond this time period records will not be counted.',
        (SELECT id FROM lu_config_field WHERE field = 'selectKey'),
        '0;Forever:1;12 Months:2;6 Months:3;Current Calendar Year:4;Current Academic Period',
        (SELECT id FROM lu_config_type WHERE type = 'Pastoral Care'),
        'true',
        'false',
        'true',
        'settings',
        (SELECT max(sequence) + 1
         FROM config c
         WHERE type_id = (SELECT id FROM lu_config_type WHERE type = 'Pastoral Care')));
