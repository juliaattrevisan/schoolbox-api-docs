-- Config option in Document Management to prevent students from accessing their reports
-- Defaults to ON (students can access reports) to minimise disturbance

insert into `config` (
name, value, title, description, field_id, options, type_id, editable, hidden, required
)
VALUES (
'academic_reports_available_to_students',
1,
'Academic Reports Available To Students',
'If enabled, students can access documents in the Academic Reports screen.',
3,
'',
20,
'true',
'false',
'false'
);

