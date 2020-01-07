INSERT INTO config(name, value, title, description, field_id, options, type_id, editable, hidden, required)
VALUES (
           'class_markbook_enable',
           '1',
           'Enable Class Markbook Module',
           'If set, Class Markbook will be available',
           (SELECT id FROM lu_config_field WHERE field = 'boolean'),
           '',
           (SELECT id FROM lu_config_type WHERE type = 'Modules'),
           'true',
           'false',
           'false'
       );

INSERT INTO config(name, value, title, description, field_id, options, type_id, editable, hidden, required)
VALUES (
           'rubric_markbook_enable',
           '1',
           'Enable Rubric Markbook Module',
           'If set, Rubric Markbook will be available',
           (SELECT id FROM lu_config_field WHERE field = 'boolean'),
           '',
           (SELECT id FROM lu_config_type WHERE type = 'Modules'),
           'true',
           'false',
           'false'
       );
