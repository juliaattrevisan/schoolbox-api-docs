INSERT INTO config (name, value, title, description, field_id, options, type_id, editable, hidden, required,
                              module, sequence)
VALUES ('view_rank', '0', 'Display Student Rank', 'Displays the student rank on the class and course markbook', (SELECT id from lu_config_field WHERE field = 'boolean'), null, (SELECT id FROM lu_config_type WHERE type = 'LMS Policy'), 'true', 'false', 'false', 'settings', 23);

UPDATE config SET sequence = sequence+1 WHERE type_id = (SELECT id FROM lu_config_type WHERE type = 'LMS Policy');

