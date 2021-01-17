-- Due Work
UPDATE msg_namespace SET label = REPLACE(label, 'Due Work', '{:dueWork}') WHERE slug = 'duework';

-- Pastoral
UPDATE msg_namespace SET label = REPLACE(label, 'Pastoral Care', '{:pastoralCare}')
WHERE slug IN ('pastoral', 'pastoral_comment', 'pastoral_log');
UPDATE msg_source SET description = REPLACE(description, 'pastoral record', '{:pastoral} record')
WHERE namespace IN (SELECT id FROM msg_namespace WHERE slug IN ('pastoral', 'pastoral_comment', 'pastoral_log'));
UPDATE msg_source SET description = REPLACE(description, 'pastoral care', '{:pastoralCare}')
WHERE namespace IN (SELECT id FROM msg_namespace WHERE slug IN ('pastoral', 'pastoral_comment', 'pastoral_log'));

-- Grades
UPDATE msg_namespace SET label = REPLACE(label, 'Grades', '{:grades}') WHERE slug = 'report_card';
UPDATE msg_source SET description = REPLACE(description, 'grades', '{:grades}')
WHERE namespace IN (SELECT id FROM msg_namespace WHERE slug = 'report_card');

-- Calendar
UPDATE msg_namespace SET label = REPLACE(label, 'Calendar', '{:calendar}') WHERE slug = 'calendar';
UPDATE msg_source SET description = REPLACE(description, 'calendar', '{:calendar}')
WHERE namespace IN (SELECT id FROM msg_namespace WHERE slug = 'calendar');

-- News
UPDATE msg_namespace SET label = REPLACE(label, 'News', '{:news}') WHERE slug IN ('news', 'news_discussion_comment');
UPDATE msg_source SET description = REPLACE(description, 'news', '{:news}')
WHERE namespace IN (SELECT id FROM msg_namespace WHERE slug IN ('news', 'news_discussion_comment'));
