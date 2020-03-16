ALTER TABLE event
ADD COLUMN class ENUM('event', 'task') NOT NULL DEFAULT 'event'
AFTER `type`;
