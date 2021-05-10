-- text/richtext is dead in our dropzone
UPDATE allowedFileTypes
SET mime = 'application/rtf'
WHERE mime = 'text/richtext';

-- avi files come through with x-msvideo mime
UPDATE allowedFileTypes
SET mime = 'video/x-msvideo'
WHERE mime = 'video/avi';