INSERT INTO config (name, value, title, description, field_id, options, type_id, editable, hidden, required,
                              module, sequence)
VALUES ('view_rank', '0', 'Display Student Rank', 'Displays the student rank on the Class Markbook', (SELECT id from lu_config_field WHERE field = 'boolean'), null, (SELECT id FROM lu_config_type WHERE type = 'LMS Policy'), 'true', 'false', 'false', 'settings', 36);

