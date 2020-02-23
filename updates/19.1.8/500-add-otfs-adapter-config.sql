-- We only want this to execute if not already done so, it has already been manually deployed to some instances.
-- We therefore need to wrap in a temp stored proc.

-- Reset delimiter so we use normal delimiter ';' inside procedure definition.
DELIMITER $$

-- Create procedure
CREATE PROCEDURE create_config_otfs_adapter()
BEGIN
    IF
        (SELECT COUNT(*) FROM config WHERE name = 'otfs_adapter') = 0
    THEN
        -- Increment all config ids so we can ensure new config immediately precedes OTFS S3 configs
        UPDATE config
        SET configID = configID + 1
        WHERE configID IN (
            SELECT * FROM (
                SELECT configID FROM config WHERE configID >= (SELECT MIN(configID) FROM config WHERE name LIKE 'otfs%')
            ) t1
        )
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
            'false',
            'false',
            'true',
            'settings'
        );

        -- Need to reset auto-increment, as we have manually inserted an ID in this sequence
        SET @nextId=(SELECT MAX(configID) FROM config) + 1;
        SET @s = CONCAT("ALTER TABLE config AUTO_INCREMENT = ",@nextId);
        PREPARE stmt FROM @s;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END $$

-- Reset delimiter back to default value.
DELIMITER ;

-- Call procedure then drop it.
CALL create_config_otfs_adapter();
DROP PROCEDURE IF EXISTS create_config_otfs_adapter;

-- Hide all OTFS configs (this is idempotent, no need to wrap in procedure)
UPDATE config SET editable = 'false' WHERE name LIKE 'otfs%';
