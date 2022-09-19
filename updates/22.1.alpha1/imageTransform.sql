alter table storage_transform
    add format enum('jpg', 'gif', 'png', 'webp') null;

delete from storage_transform where storage_transform.format is null;

INSERT INTO allowedFileTypes (description, mime, iconFile, inline, enable)
VALUES ('Apple HEIC', 'image/heic', 'application-image-jpg.svg', 'false', 'true');

INSERT INTO allowedFileTypes (description, mime, iconFile, inline, enable)
VALUES ('Apple HEIF', 'image/heif', 'application-image-jpg.svg', 'false', 'true');

INSERT INTO allowedFileExtension (fileTypeID, extension)
VALUES ((SELECT fileTypeID from allowedFileTypes where description = 'Apple HEIC'), 'heic');

INSERT INTO allowedFileExtension (fileTypeID, extension)
VALUES ((SELECT fileTypeID from allowedFileTypes where description = 'Apple HEIF'), 'heif');

#Add and set default transcodable field for allowed file types
alter table allowedFileTypes
    add transcodable enum ('true', 'false') default 'true';

#default is transcodable=true, so set things that are not our list of images to false
UPDATE allowedFileTypes SET transcodable = 'false' WHERE mime NOT IN (
    'image/bmp', 'image/gif', 'image/heic', 'image/heif', 'image/jpeg', 'image/pjpeg', 'image/png', 'image/svg+xml', 'image/webp', 'image/tiff'
                                                                     );
