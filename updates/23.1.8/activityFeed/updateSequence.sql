-- Update Tooltip for student activity feed toggle
UPDATE `config`
SET
    `description` = "If toggled to ' ON ', feedback on learning activities can be shown on the student activity feed."
WHERE
    `name` = 'activity_feed_feedback_enabled';
