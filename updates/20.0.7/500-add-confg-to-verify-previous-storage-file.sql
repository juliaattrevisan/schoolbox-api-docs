INSERT INTO config (name, value, title, description, field_id, type_id, editable, hidden, required, module)
VALUES (
        'otfs_verify_previous_file',
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
