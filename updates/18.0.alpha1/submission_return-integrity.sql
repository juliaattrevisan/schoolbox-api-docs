-- The purpose of this script is ultimately to apply FKs to the following fields:
-- * submission_return.marker
-- * submission_return.owner
-- To apply the keys, we need to fix any broken data.
-- In the case of feedback given to hard-deleted students, we can hard-delete the feedback.
-- In the case of feedback given by hard-deleted teachers, we will try to assign to 'Alaress',
-- and hard-delete if a single identifiable 'Alaress' user is not available.
-- Since submission_return is linked to from other tables, we need to clean them up too.

-- In summary, we will hard-delete a submission_return if:
-- * the student is hard-deleted, or
-- * the teacher is hard-deleted AND no single identifiable 'Alaress' user is available

-- The crocodoc archive log has an optional relationship to submission_return_file,
-- which has a mandatory relationship to submission_return.
-- If we are going to hard-delete a submission return, we must hard-delete any linked files, and break the link from
-- any crocodoc archive records linked to them.
UPDATE student_submission_file_crocodoc_archive_log ssfcal
JOIN submission_return_file f ON ssfcal.submission_return_file_id = f.id
JOIN submission_return sr ON f.submission_return_id = sr.id
LEFT JOIN user m_u ON m_u.id = sr.marker
LEFT JOIN user o_u ON o_u.id = sr.owner
SET ssfcal.submission_return_file_id = NULL
WHERE (m_u.id IS NULL AND 1 != (SELECT COUNT(*) FROM user u WHERE u.username LIKE 'alaress'))
OR o_u.id IS NULL;

-- Hard-delete any submission_return_file which is linked to an invalid submission return
DELETE srf
FROM submission_return_file srf
JOIN submission_return sr ON srf.submission_return_id = sr.id
LEFT JOIN user m_u ON m_u.id = sr.marker
LEFT JOIN user o_u ON o_u.id = sr.owner
WHERE (m_u.id IS NULL AND 1 != (SELECT COUNT(*) FROM user u WHERE u.username LIKE 'alaress'))
OR o_u.id IS NULL;

-- Hard-delete any unrecoverable submission_returns
DELETE sr
FROM submission_return sr
LEFT JOIN user m_u ON m_u.id = sr.marker
LEFT JOIN user o_u ON o_u.id = sr.owner
WHERE (m_u.id IS NULL AND 1 != (SELECT COUNT(*) FROM user u WHERE u.username LIKE 'alaress'))
OR o_u.id IS NULL;


-- Assign any feedback from missing teachers to 'Alaress'
-- (If no 'alaress' user exists, these records would have been hard-deleted earlier)
UPDATE submission_return sr
LEFT JOIN user u ON u.id = sr.marker
SET sr.marker = (SELECT id FROM user WHERE username LIKE 'alaress')
WHERE u.id IS NULL;

-- Fix the nullability of the 'update_at' field.
-- If null, assume it has never been updated and set it to created time.
UPDATE submission_return
SET updated_at = created_at
WHERE updated_at IS NULL;

ALTER TABLE submission_return
CHANGE COLUMN updated_at updated_at DATETIME NOT NULL;

-- We can now reliably add the missing FKs

-- submission_return.marker
ALTER TABLE submission_return
ADD CONSTRAINT `fk_submission_return_marker`
FOREIGN KEY (marker) REFERENCES user (id)
ON DELETE NO ACTION
ON UPDATE NO ACTION;

-- submission_return.owner
ALTER TABLE submission_return
CHANGE COLUMN owner owner INT(10) UNSIGNED NOT NULL;

ALTER TABLE submission_return
ADD CONSTRAINT `fk_submission_return_owner`
FOREIGN KEY (owner) REFERENCES user (id)
ON DELETE NO ACTION
ON UPDATE NO ACTION;
