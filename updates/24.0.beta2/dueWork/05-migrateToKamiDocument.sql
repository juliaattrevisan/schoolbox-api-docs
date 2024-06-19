INSERT INTO kami_document (id, kami_document_id, status, error_message, kami_created_at, created_at, updated_at, deleted_at)
SELECT id, kami_document_id, status, error_message, kami_created_at, created_at, updated_at, deleted_at
FROM _student_submission_file_kami;

UPDATE student_submission_file ssf
INNER JOIN _student_submission_file_kami ssfk ON ssf.id = ssfk.student_submission_file_id
SET ssf.kami_document_id = ssfk.id;
