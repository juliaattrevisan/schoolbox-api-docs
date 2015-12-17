-- Ensure that a `text\plain` file type exists.
-- (Restore it if it's soft-deleted.)
INSERT INTO `allowedFileTypes` (
    `description`,
    `mime`,
    `iconFile`,
    `inline`,
    `enable`,
    `max_file_size`,
    `deleted`
) VALUES (
    'Plain Text',
    'text/plain',
    'txt.png',
    'true',
    'true',
    0,
    0
) ON DUPLICATE KEY UPDATE `deleted` = 0;

-- And add an extension for it.
INSERT IGNORE INTO `allowedFileExtension`
    SELECT `fileTypeID`, 'log' FROM `allowedFileTypes` WHERE `mime` = 'text/plain';
