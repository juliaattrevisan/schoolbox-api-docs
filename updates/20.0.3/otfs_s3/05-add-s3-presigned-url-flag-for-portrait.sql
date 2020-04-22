-- Increment all config ids so we can insert new config immediately after OTFS S3 configs

UPDATE config
SET configID = configID + 1
WHERE configID IN (
    SELECT * FROM (
        SELECT configID FROM config WHERE configID > (SELECT MAX(configID) FROM config WHERE name LIKE 'otfs%')
    ) t1
)
ORDER BY configID DESC;

INSERT INTO config (
    configID,
    name,
    value,
    title,
    description,
    field_id,
    type_id,
    editable,
    hidden,
    required,
    module
) VALUES (
    (SELECT * FROM (SELECT MAX(configID) FROM config WHERE name LIKE 'otfs%') t1) + 1,
    'otfs_portrait_s3_presigned_urls_enabled',
    '0',
    'Enabled portrait.php AWS S3 PreSigned URLs',
    'If enabled, files stored in S3 will be served directly from S3 to clients for portrait.php, without streaming via Schoolbox',
    (SELECT id FROM lu_config_field WHERE field = 'boolean'),
    (SELECT id FROM lu_config_type WHERE type = '3rd Party Services'),
    'false',
    'false',
    'true',
    'settings'
);

-- Need to reset auto-increment, as we have manually inserted an ID in this sequence
SET @s = CONCAT("ALTER TABLE config AUTO_INCREMENT = ",(SELECT MAX(configID) FROM config) + 1);
PREPARE stmt FROM @s;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
