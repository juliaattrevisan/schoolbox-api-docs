-- Normally this would be part of the migration process but it's just too awkward now
-- Aftermarket these user-facing descriptions instead.
--
-- These probably need to be written differently, but this will suffice for now; it's better than empty

UPDATE config
SET `description` = 'Configure AI settings for interactive text editors.'
WHERE name = 'ai_text_chat'
LIMIT 1;

UPDATE config
SET `description` = 'Configure AI settings for generating lesson plan content.'
WHERE name = 'ai_lessonplan'
LIMIT 1;

UPDATE config
SET `description` = 'Configure AI settings for discussion learning activities.'
WHERE name = 'ai_dialogagent'
LIMIT 1;

UPDATE config
SET `description` = 'Configure AI settings for differentiation profile support in discussion learning activities.'
WHERE name = 'ai_differentiation'
LIMIT 1;

UPDATE config
SET `description` = 'Configure AI settings for image generation.'
WHERE name = 'ai_image_generation'
LIMIT 1;

UPDATE config
SET `description` = 'Configure AI settings for learning activity customisation.'
WHERE name = 'ai_learning_activity_customisation'
LIMIT 1;