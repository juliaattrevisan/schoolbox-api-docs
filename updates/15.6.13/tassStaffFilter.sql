INSERT INTO config (name, value, title, description, field_id, options, type_id, editable, hidden, required)
VALUES ('tass_staff_dir_position_exclude', 'trainee', 'Staff Directory Positions To Exclude', 'Remove staff containing this text in their position titles from staff directory report, eg ''trainee,relief''', 1, '', 7, 'true', 'false', 'false');

INSERT INTO config (name, value, title, description, field_id, options, type_id, editable, hidden, required)
VALUES ('tass_staff_dir_award_exclude', 'board', 'Staff Directory Award Types To Exclude', 'Remove staff containing this award type, eg ''director,board''', 1, '', 7, 'true', 'false', 'false');

INSERT INTO config (name, value, title, description, field_id, options, type_id, editable, hidden, required)
VALUES ('tass_staff_dir_code_mappings', 'TE,Teacher,SO,Corporate Staff - Full Time,SOTT,Corporate Staff - Full Time,SOC,Corporate Staff - Term Time or Casual,HOC,Corporate Staff - Casual', 'Staff Directory Category Code Mappings', 'Remove staff containing this text in their position titles from staff directory report, eg ''trainee,relief''', 1, '', 7, 'true', 'false', 'false');
