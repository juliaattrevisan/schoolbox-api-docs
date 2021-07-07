-- column to hold pastoral attachment real name
ALTER TABLE pastoral_attachment
    ADD COLUMN realname VARCHAR(255) DEFAULT NULL AFTER hash;
ALTER TABLE pastoral_upload_queue
    ADD COLUMN realname VARCHAR(255) DEFAULT NULL AFTER hash;
-- set attachment name from storage table
UPDATE pastoral_attachment JOIN storage
ON pastoral_attachment.hash = storage.hash
    SET pastoral_attachment.realname = storage.filename;
UPDATE pastoral_upload_queue JOIN storage
ON pastoral_upload_queue.hash = storage.hash
    SET pastoral_upload_queue.realname = storage.filename;
