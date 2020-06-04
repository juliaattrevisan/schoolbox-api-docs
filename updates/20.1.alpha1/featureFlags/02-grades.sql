# Feature flag for grades page.
# Don't care about sequence, as this is hidden.
INSERT INTO config(
    name,
    value,
    title,
    description,
    field_id,
    options,
    type_id,
    editable,
    hidden,
    required
) VALUES (
    'grades_enabled',
    '0',
    'Enable Grades Module',
    'If toggled to ''ON'', the grades page for students will be enabled.',
    (SELECT id FROM lu_config_field WHERE field = 'boolean'),
    '',
    (SELECT id FROM lu_config_type WHERE type = 'Modules'),
    'true',
    'true',
    'false'
);
