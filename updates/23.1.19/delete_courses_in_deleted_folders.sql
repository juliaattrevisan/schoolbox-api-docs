# Mark as deleted, all course implementations in deleted folders.
UPDATE course_implementation
SET deleted_at=NOW()
WHERE id IN (
    SELECT ci.id
    FROM course_implementation ci
    JOIN folder f ON f.id = ci.folder_id
    WHERE f.deleted_at IS NOT NULL
);
