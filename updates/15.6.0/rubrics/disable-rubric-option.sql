/* Keep rubric in the system, but disable it from being selectable */
UPDATE submission_mark_type
SET enabled = FALSE
WHERE slug = 'rubric';
