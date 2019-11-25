-- Controls whether grades appear is red/green 
INSERT IGNORE INTO `config` (name, value, title, description, field_id, options, type_id, editable, hidden, required, module)
VALUES (
           'grade gradient',
           '1',
           'Grade Colors',
           'If on, grades appear red to green based on value. If off, all grades appaear black.',
           (SELECT id from lu_config_field WHERE field = 'Boolean'),
           null,
           (SELECT id from lu_config_type WHERE type = 'LMS Policy'),
           'true',
           'false',
           'true',
           'settings'
       );