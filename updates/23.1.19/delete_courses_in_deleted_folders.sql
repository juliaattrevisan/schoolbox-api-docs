# Mark as deleted, all course implementations in deleted folders.
UPDATE course_implementation
SET deleted_at=NOW()
WHERE folder_id IN (
    SELECT id
    FROM folder
    WHERE deleted_at IS NOT NULL
);
