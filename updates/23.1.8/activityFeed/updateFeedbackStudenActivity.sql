-- Update Tooltip for student activity feed toggle

UPDATE `config`
SET
    `description` = "If toggled to ' ON ', feedback on learning activities can be shown on the student activity feed."
WHERE
    `name` = 'activity_feed_feedback_enabled';

-- Push down all the other configs in this area
-- Create a hole in config

UPDATE config c
INNER JOIN lu_config_type lct on c.type_id = lct.id
SET
    c.sequence = c.sequence + 1
WHERE
    lct.type = 'LMS Policy'
    AND c.sequence > (
        SELECT c2.sequence
        FROM config c2
        WHERE
            c2.name = 'restrict_student_submissions'
        LIMIT 1
    );

UPDATE `config`
SET
    `sequence` = (
        SELECT c2.sequence
        FROM config c2
        WHERE
            c2.name = 'restrict_student_submissions'
        LIMIT 1
    ) + 1
WHERE
    `name` = 'activity_feed_feedback_enabled';
