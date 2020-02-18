-- Increment all config ids so we can ensure new config immediately precedes OTFS S3 configs
UPDATE config
SET configID = configID + 1
WHERE configID IN (SELECT * FROM (
    SELECT configID FROM config WHERE configID >= (SELECT MIN(configID) FROM config WHERE name LIKE 'otfs%')
) t1)
ORDER BY configID DESC;

-- Insert new config
INSERT INTO config (
    configID,
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
    (SELECT * FROM (SELECT MIN(configID) FROM config WHERE name LIKE 'otfs%') t1) - 1,
    'otfs_adapter',
    IF(
        -- If any 'otfs' config are empty, set to 'local', otherwise set to 's3'
        (SELECT * FROM (SELECT COUNT(*) FROM config WHERE name LIKE 'otfs%' AND TRIM(COALESCE(value, '')) = '') t2),
        'local',
        'awsS3'
    ),
    'File System Adapter',
    'The file system to use to store Schoolbox files.',
    (SELECT id FROM lu_config_field WHERE field = 'selectKey'),
    'local;Local:awsS3;AWS S3',
    (SELECT id FROM lu_config_type WHERE type = '3rd Party Services'),
    'true',
    'false',
    'true',
    'settings'
);

