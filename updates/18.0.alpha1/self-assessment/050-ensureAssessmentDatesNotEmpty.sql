-- Ensure that no assessments have a 0000-00-00 create or update date,
-- as these dates cause needless warnings when hydrated.
UPDATE assessment SET created_at = COALESCE(
    NULLIF(updated_at,   '0000-00-00 00:00:00'),
    NULLIF(publish_date, '0000-00-00 00:00:00'),
    NULLIF(open_date,    '0000-00-00 00:00:00'),
    NULLIF(due_date,     '0000-00-00 00:00:00'),
    NULLIF(close_date,   '0000-00-00 00:00:00'),
    NOW()
) WHERE created_at = '0000-00-00 00:00:00';
UPDATE assessment SET updated_at = COALESCE(
    NULLIF(created_at,   '0000-00-00 00:00:00'),
    NULLIF(publish_date, '0000-00-00 00:00:00'),
    NULLIF(open_date,    '0000-00-00 00:00:00'),
    NULLIF(due_date,     '0000-00-00 00:00:00'),
    NULLIF(close_date,   '0000-00-00 00:00:00'),
    NOW()
) WHERE updated_at = '0000-00-00 00:00:00';
