### add jfif into the DB
INSERT IGNORE INTO allowedFileExtension (fileTypeID, extension)
VALUES ((SELECT fileTypeID from allowedFileTypes where mime = 'image/jpeg'), 'jfif');
