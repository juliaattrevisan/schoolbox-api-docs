-- No way to access/recover orphan attachments if the pastoral record does not exist
DELETE a FROM pastoral_attachment a
  LEFT JOIN pastoral p ON p.id = a.pastoral_id
  WHERE p.id IS NULL;

-- Ensure this field is never NULL
ALTER TABLE pastoral_attachment
  DROP FOREIGN KEY fk_pastoral_attachment;

ALTER TABLE pastoral_attachment
  CHANGE COLUMN pastoral_id pastoral_id INT(11) NOT NULL;

ALTER TABLE pastoral_attachment
  ADD CONSTRAINT fk_pastoral_attachment
  FOREIGN KEY (pastoral_id) REFERENCES pastoral (id)
  ON DELETE NO ACTION
  ON UPDATE NO ACTION;

-- No way to access/recover orphan attachments if the HASH is invalid
DELETE a FROM pastoral_attachment a
  LEFT JOIN storage s ON a.hash = s.hash
WHERE s.hash IS NULL;

-- Protect against code errors inserting NULL hashes
ALTER TABLE pastoral_attachment
  CHANGE COLUMN hash hash VARCHAR(100) NOT NULL COLLATE 'ascii_general_ci';

-- Ensure referential integrity
ALTER TABLE pastoral_attachment
  ADD CONSTRAINT fk_pastoral_attachment_hash
  FOREIGN KEY (hash) REFERENCES storage (hash)
  ON DELETE NO ACTION
  ON UPDATE NO ACTION;

-- This should not allow NULLs
-- Preserve default in case there was some semantic purpose
UPDATE pastoral_attachment
  SET created_at = '0000-00-00 00:00:00'
  WHERE created_at IS NULL;

ALTER TABLE pastoral_attachment
  CHANGE COLUMN created_at created_at DATETIME NOT NULL;

-- This should not allow NULLs
-- Preserve default in case there was some semantic purpose
UPDATE pastoral_attachment
  SET updated_at = '0000-00-00 00:00:00'
  WHERE updated_at IS NULL;

ALTER TABLE pastoral_attachment
  CHANGE COLUMN updated_at updated_at DATETIME NOT NULL;



