UPDATE openai_log SET feature = 'chat' WHERE feature IS NULL;
ALTER TABLE openai_log CHANGE COLUMN feature feature enum('chat','lessonplan','image','differentiation','dialogagent','learningactivitycustomisation') NOT NULL DEFAULT 'chat';
