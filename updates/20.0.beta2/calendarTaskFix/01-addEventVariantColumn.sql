ALTER TABLE event
ADD COLUMN variant ENUM('event', 'task') NOT NULL DEFAULT 'event'
AFTER `type`;
