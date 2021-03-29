-- Remove Quizlet
-- This is the only non-editable field representing a Quizlet concept
UPDATE `lti_provider` SET `deleted_at` = NOW()
WHERE (`url` = 'https://www.edu-apps.org/lti_public_resources/?tool_id=quizlet');
