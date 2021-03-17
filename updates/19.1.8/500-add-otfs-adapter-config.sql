-- We only want this to execute if not already done so, it has already been manually deployed to some instances.
-- Insert new config
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
    module
) VALUES (
    'otfs_adapter',
    -- If any 'otfs' config are empty, set to 'local', otherwise set to 's3'
    IF(
         (SELECT * FROM (SELECT COUNT(*) FROM config WHERE name LIKE 'otfs%' AND TRIM(COALESCE(value, '')) = '') t2),
         'local',
         'awsS3'
    ),
    'File System Adapter',
    'The file system to use to store Schoolbox files.',
    (SELECT id FROM lu_config_field WHERE field = 'selectKey'),
    'local;Local:awsS3;AWS S3',
    (SELECT id FROM lu_config_type WHERE type = '3rd Party Services'),
    'false',
    'false',
    'true',
    'settings'
) ON DUPLICATE KEY UPDATE
    -- If any 'otfs' config are empty, set to 'local', otherwise set to 's3'
    value = IF(
        (SELECT * FROM (SELECT COUNT(*) FROM config WHERE name LIKE 'otfs%' AND TRIM(COALESCE(value, '')) = '') t2),
        'local',
        'awsS3'
    ),
    title = 'File System Adapter',
    description = 'The file system to use to store Schoolbox files.',
    field_id = (SELECT id FROM lu_config_field WHERE field = 'selectKey'),
    options = 'local;Local:awsS3;AWS S3',
    type_id = (SELECT id FROM lu_config_type WHERE type = '3rd Party Services'),
    editable = 'false',
    hidden = 'false',
    required = 'true',
    module = 'settings';

-- Hide all OTFS configs (this is idempotent, no need to wrap in procedure)
UPDATE config SET editable = 'false' WHERE name LIKE 'otfs%';
