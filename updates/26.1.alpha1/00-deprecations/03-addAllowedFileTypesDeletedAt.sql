ALTER TABLE allowedFileTypes ADD deleted_at DATETIME NULL AFTER max_file_size;
CREATE INDEX deleted_at ON allowedFileTypes (deleted_at);
