INSERT INTO config (name, value, title, description, field_id, type_id, editable, hidden, required, module)
VALUES (
           'aws_s3_dr_backup_visibility',
           '0',
           'Disaster Recover feature visibility',
           'Controls whether the Disaster Recovery feature is visible in the system',
           (SELECT id FROM lu_config_field WHERE field = 'boolean'),
           (SELECT id FROM lu_config_type WHERE type = 'Resources'),
           'false',
           'true',
           'true',
           'settings'
       );

INSERT INTO config (name, value, title, description, field_id, type_id, editable, hidden, required, module)
VALUES (
           'aws_s3_dr_backup_enabled',
           '0',
           'Enable re-upload of missing or corrupted files',
           'When enabled and a file is re-uploaded, the system will check that the previous copy on disk is present and valid, overwriting with the new copy if required. Enabling this will slow down file uploads.',
           (SELECT id FROM lu_config_field WHERE field = 'boolean'),
           (SELECT id FROM lu_config_type WHERE type = 'Resources'),
           'true',
           'false',
           'true',
           'settings'
       );

INSERT INTO config (name, value, title, description, field_id, type_id, editable, hidden, required, module)
VALUES (
           'aws_s3_dr_sync_status',
           '',
           'Disaster Recover tool status',
           'Status of most recent execution of the Disaster Recovery feature',
           (SELECT id FROM lu_config_field WHERE field = 'input'),
           (SELECT id FROM lu_config_type WHERE type = 'Resources'),
           'false',
           'true',
           'false',
           'settings'
       );
