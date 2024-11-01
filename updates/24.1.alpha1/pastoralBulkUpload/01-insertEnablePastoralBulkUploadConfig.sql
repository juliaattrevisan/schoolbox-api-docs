SELECT @fieldId := id FROM lu_config_field WHERE field = 'boolean';
SELECT @typeId := id FROM lu_config_type WHERE type = 'Pastoral Care';
SELECT @sequence := MAX(sequence) FROM config WHERE type_id = @typeId;

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
    'pastoral_bulk_upload_enabled',
    '1',
    'Enable Pastoral Record Bulk Upload',
    'If toggled to ''ON'', pastoral moderators may bulk upload reports in a ZIP file into pastoral records for multiple students at once.',
    @fieldId,
    '',
    @typeId,
    'true',
    'false',
    'false',
    'settings',
    @sequence + 1
);
