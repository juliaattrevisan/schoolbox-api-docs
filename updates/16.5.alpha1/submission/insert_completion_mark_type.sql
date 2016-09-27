-- Inserts the 'Completion' MarkType that will be used by Tasks.
-- It is disabled, which means it won't be available to select for other IAssessables.
-- At some point in the future, it may be enabled.
INSERT INTO submission_mark_type (`mark_type_id`,`hr_name`,`slug`,`comment`,`enabled`)
VALUES (7, 'Completion', 'completion', "Either 'Not Completed', 'Student-Assessed', or 'Teacher-Assessed'", 0);