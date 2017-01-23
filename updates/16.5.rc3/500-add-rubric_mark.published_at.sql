-- Convert 'tstamp' to 'created_at'
-- Insert 'published_at' to make consistent with submissions and enable identification of drafts
ALTER TABLE rubric_mark
    CHANGE COLUMN tstamp created_at DATETIME NOT NULL,
    ADD COLUMN published_at DATETIME NULL DEFAULT NULL AFTER created_at;

-- Up until now, no such thing as drafts. So all marks were only touched once.
UPDATE rubric_mark
SET published_at = created_at;

