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
    '1',
    'Enable Grades Module',
    'If toggled to ''ON'', the grades module (for displaying a student''s completed assessments and feedback) will be enabled. If toggled to ''OFF'', it will be disabled for all users.',
    (SELECT id FROM lu_config_field WHERE field = 'boolean'),
    '',
    (SELECT id FROM lu_config_type WHERE type = 'Modules'),
    'true',
    'true',
    'false'
);
