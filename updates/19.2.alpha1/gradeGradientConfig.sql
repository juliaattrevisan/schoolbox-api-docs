-- Controls whether grades appear is red/green
INSERT IGNORE INTO `config` (name, value, title, description, field_id, options, type_id, editable, hidden, required, module)
VALUES (
           'grade_gradient',
           '1',
           'Grade Colors',
           'If on, grades appear red to green based on value. If off, all grades appear in black.',
           (SELECT id from lu_config_field WHERE field = 'boolean'),
           null,
           (SELECT id from lu_config_type WHERE type = 'LMS Policy'),
           'true',
           'false',
           'false',
           'settings'
       );
