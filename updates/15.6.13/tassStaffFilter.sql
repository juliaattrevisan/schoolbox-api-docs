INSERT INTO config (name, value, title, description, field_id, options, type_id, editable, hidden, required)
VALUES ('tass_staff_dir_position_exclude', '', 'Staff Directory Positions To Exclude', 'Remove staff containing this text in their position titles from staff directory report, eg ''trainee,relief''', 1, '', 7, 'true', 'false', 'false');

INSERT INTO config (name, value, title, description, field_id, options, type_id, editable, hidden, required)
VALUES ('tass_staff_dir_award_exclude', '', 'Staff Directory Award Types To Exclude', 'Remove staff containing this award type, eg ''director,board''', 1, '', 7, 'true', 'false', 'false');

INSERT INTO config (name, value, title, description, field_id, options, type_id, editable, hidden, required)
VALUES ('tass_staff_dir_code_mappings', '', 'Staff Directory Category Code Mappings', 'Replace category codes with friendly descriptions, eg ''teach,Teacher,admin,School Administration (must appear in pairs)'', 1, '', 7, 'true', 'false', 'false');
