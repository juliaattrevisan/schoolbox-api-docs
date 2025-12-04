SELECT @fieldId  := id FROM lu_config_field WHERE field = 'uri';
SELECT @typeId   := id FROM lu_config_type WHERE type = 'AI';
SELECT @sequence := sequence FROM config WHERE type_id = @typeId AND module = 'settings';
INSERT INTO config SET
    name        = 'presidio_analysis_host',
    value       = '',
    title       = 'Presidio Analysis Host',
    description = 'The URL for the Presidio anonymisation platform’s analysis service.',
    field_id    = @fieldId,
    options     = '',
    type_id     = @typeId,
    editable    = 'false',
    hidden      = 'true',
    required    = 'false',
    module      = 'settings',
    sequence    = @sequence + 1;
INSERT INTO config SET
    name        = 'presidio_anonymisation_host',
    value       = '',
    title       = 'Presidio Anonymisation Host',
    description = 'The URL for the Presidio anonymisation platform’s anonymisation service.',
    field_id    = @fieldId,
    options     = '',
    type_id     = @typeId,
    editable    = 'false',
    hidden      = 'true',
    required    = 'false',
    module      = 'settings',
    sequence    = @sequence + 1;
