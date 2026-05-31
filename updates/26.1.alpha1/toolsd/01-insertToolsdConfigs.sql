SELECT @fieldId  := id FROM lu_config_field WHERE field = 'uri';
SELECT @typeId   := id FROM lu_config_type WHERE type = 'Resources';
SELECT @sequence := COALESCE(MAX(sequence), 0) FROM config WHERE type_id = @typeId AND module = 'settings';
INSERT INTO config SET
    name        = 'toolsd_host',
    value       = '',
    title       = 'Tools Daemon Host',
    description = 'The hostname on which the tools daemon service is accessible: job requests are sent to this HTTP API.',
    field_id    = @fieldId,
    options     = '',
    type_id     = @typeId,
    editable    = 'false',
    hidden      = 'true',
    required    = 'false',
    module      = 'settings',
    sequence    = @sequence + 1;
