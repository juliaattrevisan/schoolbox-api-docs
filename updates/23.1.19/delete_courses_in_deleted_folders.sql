# Mark as deleted, all course implementations in deleted folders.
# Set the deleted_at time for the course to match the folder.delete_at time.
UPDATE course_implementation ci
JOIN folder f ON f.id = ci.folder_id
SET ci.deleted_at = f.deleted_at
WHERE f.deleted_at IS NOT NULL;
