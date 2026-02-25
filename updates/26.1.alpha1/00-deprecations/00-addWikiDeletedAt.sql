ALTER TABLE wiki ADD deleted_at DATETIME NULL AFTER created;
CREATE INDEX deleted_at ON wiki (deleted_at);
