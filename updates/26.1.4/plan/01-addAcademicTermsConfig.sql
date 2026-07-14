SELECT @academicPeriodEnabled := IF(
    EXISTS (
        SELECT 1
        FROM config
        WHERE name = 'lms_enabled'
          AND value IN ('1', 'true')
    )
    OR EXISTS (
        SELECT 1
        FROM config
        WHERE name = 'site_plan'
          AND value IN ('Community-Enhanced', 'Community-Elite')
    ),
    '1',
    '0'
);
SELECT @fieldId  := id FROM lu_config_field WHERE field = 'boolean';
SELECT @typeId   := id FROM lu_config_type WHERE type = 'Modules';
SELECT @sequence := COALESCE(MAX(sequence), 0) FROM config WHERE type_id = @typeId AND module = 'settings';

INSERT INTO config SET
    name        = 'academic_period_enabled',
    value       = @academicPeriodEnabled,
    title       = 'Academic Period Enabled',
    description = 'Controls whether Academic Period administration is visible.',
    field_id    = @fieldId,
    options     = NULL,
    type_id     = @typeId,
    editable    = 'false',
    hidden      = 'true',
    required    = 'false',
    module      = 'settings',
    sequence    = @sequence + 1;
