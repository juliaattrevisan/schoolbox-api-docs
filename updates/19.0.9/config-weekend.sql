-- Assessment Calendar Feature Flag
INSERT INTO `config` (name, value, title, description, field_id, options, type_id, editable, hidden, required, module)
VALUES (
           'locale_weekend',
           '6,0',
           'Days of Weekend',
           'The day numbers that make up the weekend, starting with Sunday at 0, ending in Saturday at 6.',
           (SELECT id from lu_config_field WHERE field = 'input'),
           null,
           (SELECT id from lu_config_type WHERE type = 'Localisation'),
           'true',
           'false',
           'true',
           'settings'
       );
