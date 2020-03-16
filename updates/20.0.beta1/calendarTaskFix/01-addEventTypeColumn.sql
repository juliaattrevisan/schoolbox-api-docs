ALTER TABLE event
ADD COLUMN event_type ENUM('event', 'task') NOT NULL DEFAULT 'event'
AFTER `type`;
