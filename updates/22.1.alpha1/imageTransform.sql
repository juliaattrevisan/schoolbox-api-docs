alter table storage_transform
    add format enum('jpg', 'gif', 'png', 'webp') null;

delete from storage_transform where storage_transform.format is null;

INSERT INTO allowedFileTypes (description, mime, iconFile, inline, enable)
VALUES ('Apple HEIC', 'image/heic', 'application-image-jpg.svg', 'true', 'true');

INSERT INTO allowedFileTypes (description, mime, iconFile, inline, enable)
VALUES ('Apple HEIF', 'image/heif', 'application-image-jpg.svg', 'true', 'true');

INSERT INTO allowedFileExtension (fileTypeID, extension)
VALUES ((SELECT fileTypeID from allowedFileTypes where description = 'Apple HEIC'), 'heic');

INSERT INTO allowedFileExtension (fileTypeID, extension)
VALUES ((SELECT fileTypeID from allowedFileTypes where description = 'Apple HEIF'), 'heif');
