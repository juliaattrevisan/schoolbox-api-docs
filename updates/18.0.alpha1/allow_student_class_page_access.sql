INSERT INTO config (name, value, title, description, field_id, options, type_id, editable, hidden, required)
VALUES ('allow_student_class_access', '1', 'Allow Involved Students To Access Pages Via Class Code Permissions',
        'If enabled, students involved in a class will be granted a minimum level of READ access to folders where the corresponding class code is linked to the folder. This will override any NONE permission otherwise applied to the student.',
        3,
        '', 15, 'true', 'false', 'false');