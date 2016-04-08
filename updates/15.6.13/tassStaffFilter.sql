INSERT INTO config (name, value, title, description, field_id, options, type_id, editable, hidden, required)
VALUES ('tass_staff_dir_position_text_exclude', '', 'TASS Staff Directory ''position_text'' To Exclude', 'Remove staff containing this value in the TASS ''position_text'' field', 1, '', 7, 'true', 'false', 'false');

INSERT INTO config (name, value, title, description, field_id, options, type_id, editable, hidden, required)
VALUES ('tass_staff_dir_position_title_exclude', '', 'TASS Staff Directory ''position_title'' To Exclude', 'Remove staff containing this value in the TASS ''position_title'' field', 1, '', 7, 'true', 'false', 'false');

INSERT INTO config (name, value, title, description, field_id, options, type_id, editable, hidden, required)
VALUES ('tass_staff_dir_award_exclude', '', 'TASS Staff Directory ''awd_code'' To Exclude', 'Remove staff containing this value in the TASS ''awd_code'' field', 1, '', 7, 'true', 'false', 'false');

INSERT INTO config (name, value, title, description, field_id, options, type_id, editable, hidden, required)
VALUES ('tass_staff_dir_award_mappings', '', 'TASS Staff Directory ''awd_code'' Code Mappings', 'Replace ''awd_code'' with friendly descriptions, eg ''teach,Teacher,admin,School Administration'' (values entered must appear in pairs)', 1, '', 7, 'true', 'false', 'false');
