ALTER TABLE `event` ADD COLUMN `completed` TINYINT DEFAULT 0 NOT NULL COMMENT 'For marking Tasks as complete or not.' AFTER `publish_date`;

/* create the task event type if it doesn't already exist */
INSERT INTO `luEventType` (eventType, eventColour)
SELECT 'Task', 'B8DDFF' FROM `luEventType`
WHERE NOT EXISTS (
  SELECT * FROM `luEventType`
  WHERE eventType='Task'
)
LIMIT 1;