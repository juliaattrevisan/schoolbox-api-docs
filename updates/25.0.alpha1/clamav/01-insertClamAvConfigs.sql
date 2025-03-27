/**
 * Create new configs for ClamAV daemon.
 */

SELECT @typeId := id
FROM lu_config_type
WHERE type = 'Security';

SELECT @sequence := COALESCE(MAX(sequence), 1)
FROM config
WHERE type_id = @typeId;

INSERT INTO config
SET name        = 'clamd_host',
    value       = '',
    title       = 'ClamAV Daemon Host',
    description = 'Hostname on which the ClamAV virus scanning daemon is running.',
    field_id    = (SELECT id FROM lu_config_field WHERE field = 'input'),
    options     = null,
    type_id     = @typeId,
    editable    = 'false',
    hidden      = 'true',
    required    = 'false',
    module      = 'settings',
    sequence    = @sequence + 1;

INSERT INTO config
SET name        = 'clamd_port',
    value       = '',
    title       = 'ClamAV Daemon Port',
    description = 'Port on which the ClamAV virus scanning daemon is running.',
    field_id    = (SELECT id FROM lu_config_field WHERE field = 'number'),
    options     = null,
    type_id     = @typeId,
    editable    = 'false',
    hidden      = 'true',
    required    = 'false',
    module      = 'settings',
    sequence    = @sequence + 2;

INSERT INTO config
SET name        = 'clamd_max_file_size',
    value       = '20 MB',
    title       = 'ClamAV Daemon Max File Size',
    description = 'Maximum file size which the ClamAV virus scanning daemon will attempt to scan.',
    field_id    = (SELECT id FROM lu_config_field WHERE field = 'bytes'),
    options     = null,
    type_id     = @typeId,
    editable    = 'false',
    hidden      = 'true',
    required    = 'false',
    module      = 'settings',
    sequence    = @sequence + 3;
