SELECT @fieldId  := id FROM lu_config_field WHERE field = 'input';
SELECT @typeId   := id FROM lu_config_type WHERE type = 'Resources';
SELECT @sequence := COALESCE(MAX(sequence), 0) FROM config WHERE type_id = @typeId AND module = 'settings';
INSERT INTO config SET
    name        = 'toolsd_api_key',
    value       = '',
    title       = 'Tools Daemon API Key',
    description = 'The API key for the tools daemon service: if provided, it will be included in all requests to the tools daemon''s API.',
    field_id    = @fieldId,
    options     = '',
    type_id     = @typeId,
    editable    = 'false',
    hidden      = 'true',
    required    = 'false',
    module      = 'settings',
    sequence    = @sequence + 1;
