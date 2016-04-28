INSERT INTO `config` (name, value, title, description, field_id, options, type_id, editable, hidden, required)
VALUES ('calendar_export_start', '1', 'Calendar Export (Months To Go Back)', 'The number of months before todays date to include in calendar exports', 1, null, 5, 'true', 'false', 'false');

INSERT INTO `config` (name, value, title, description, field_id, options, type_id, editable, hidden, required)
VALUES ('calendar_export_end', '12', 'Calendar Export (Months To Go Forward)', 'The number of months after todays date to include in calendar exports', 1, null, 5, 'true', 'false', 'false');

INSERT INTO `config` (name, value, title, description, field_id, options, type_id, editable, hidden, required)
VALUES ('timetable_export_end', '3', 'Timetable Calendar Export (Months To Go Forward)', 'The number of months after todays date to include in timetable calendar exports', 1, null, 5, 'true', 'false', 'false');