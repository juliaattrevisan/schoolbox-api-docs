# Feature flag for upcoming assessments page.
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
    'upcoming_assessments_enabled',
    '1',
    'Enable Upcoming Assessments Module',
    'If toggled to ''ON'', the upcoming assessments module (for displaying a student''s upcoming assessments in a calendar) will be enabled. If toggled to ''OFF'', it will be disabled for all users.',
    (SELECT id FROM lu_config_field WHERE field = 'boolean'),
    '',
    (SELECT id FROM lu_config_type WHERE type = 'Modules'),
    'true',
    'true',
    'false'
);
