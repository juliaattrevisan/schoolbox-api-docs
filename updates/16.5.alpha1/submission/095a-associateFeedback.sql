-- Make feedback associated when crocodoc uuids exist in both s and r
UPDATE submission_return r
RIGHT JOIN student_submission s ON r.uuid = s.uuid
SET r.student_submission_id = s.id
WHERE r.student_submission_id IS NULL;