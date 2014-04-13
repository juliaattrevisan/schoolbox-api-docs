UPDATE component_definition SET `name` = 'Quizzes' WHERE class LIKE 'Component_Homepage_AssessController';

UPDATE error SET message = REPLACE(message,'online assessment', 'quiz') WHERE message LIKE '%online assessment%';
UPDATE error SET message = REPLACE(message,'Online Assessment', 'quiz') WHERE message LIKE '%online assessment%';

UPDATE error SET message = REPLACE(message,'assessment', 'quiz') WHERE message LIKE '%assessment%';