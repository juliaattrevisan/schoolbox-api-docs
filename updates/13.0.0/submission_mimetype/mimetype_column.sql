-- Expand mime_type column to same char width as allowedFileTypes.mimetype
ALTER TABLE student_submission CHANGE COLUMN `mime_type` `mime_type` VARCHAR(100) NULL DEFAULT NULL;
