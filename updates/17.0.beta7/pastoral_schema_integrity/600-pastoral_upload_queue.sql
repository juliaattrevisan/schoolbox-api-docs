-- Unrecoverable if not linked to pastoral
DELETE q FROM pastoral_upload_queue q
  LEFT JOIN pastoral p ON q.pastoral_id = p.id
  WHERE p.id IS NULL;

ALTER TABLE pastoral_upload_queue
  CHANGE COLUMN pastoral_id pastoral_id INT(11) NOT NULL;

ALTER TABLE pastoral_upload_queue
  ADD CONSTRAINT fk_pastoral_upload_queue_pastoral_id
  FOREIGN KEY (pastoral_id) REFERENCES pastoral(id)
  ON UPDATE NO ACTION
  ON DELETE NO ACTION;

-- Unrecoverable if not linked to storage file
DELETE q FROM pastoral_upload_queue q
  LEFT JOIN storage s ON s.hash = q.hash
  WHERE s.hash IS NULL;

ALTER TABLE pastoral_upload_queue
  CHANGE COLUMN hash hash VARCHAR(100) NOT NULL COLLATE 'ascii_general_ci';

ALTER TABLE pastoral_upload_queue
  ADD CONSTRAINT fk_pastoral_upload_queue_hash
  FOREIGN KEY (hash) REFERENCES storage(hash)
  ON UPDATE NO ACTION
  ON DELETE NO ACTION;

-- This should not permit NULL
UPDATE pastoral_upload_queue
  SET status = ''
  WHERE status IS NULL;

ALTER TABLE pastoral_upload_queue
  CHANGE COLUMN status status VARCHAR(255) NOT NULL;


-- This should not allow NULLs
UPDATE pastoral_upload_queue
SET created_at = '0000-00-00 00:00:00'
WHERE created_at IS NULL;

ALTER TABLE pastoral_upload_queue
  CHANGE COLUMN created_at created_at DATETIME NOT NULL;

-- This should not allow NULLs
UPDATE pastoral_upload_queue
SET updated_at = '0000-00-00 00:00:00'
WHERE updated_at IS NULL;

ALTER TABLE pastoral_upload_queue
  CHANGE COLUMN updated_at updated_at DATETIME NOT NULL;
