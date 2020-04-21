-- This is to drop unique restriction from the column
ALTER TABLE newsTopic DROP KEY idx_newsTopic_slug;
ALTER TABLE newsTopic ADD INDEX idx_newsTopic_slug (slug);
