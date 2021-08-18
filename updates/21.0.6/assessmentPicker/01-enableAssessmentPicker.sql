INSERT INTO config (
    name,
    value,
    title,
    description,
    field_id,
    options,
    type_id,
    editable,
    hidden,
    required,
    module,
    sequence
) VALUES (
    'assessment_picker_enabled',
    '1',
    'Enable Assessment Date Picker',
    'If ON, the assessment date picker (which displays the upcoming assessments for students in the class) is enabled when selecting a due date for assessments. If OFF, the standard date picker will be shown instead.',
    (SELECT id FROM lu_config_field WHERE field = 'boolean'),
    null,
    (SELECT id FROM lu_config_type WHERE type = 'Modules'),
    'true',
    'true',
    'false',
    'settings',
    null
);
