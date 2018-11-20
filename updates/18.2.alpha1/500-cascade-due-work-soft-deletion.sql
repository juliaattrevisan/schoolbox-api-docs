-- This script represents the soft-delete cascade tree for DueWork.
--
-- Note that it is an invalid state if a parent is deleted AND:
-- * child is not deleted, OR
-- * child is deleted after the parent
-- In both cases, we set the child's deleted_at date to the parent.
--
-- We can perform a top-down traversal to fix the data, sweeping deleted_at dates down the tree.


-- Eportfolio Showcase Items
UPDATE assessment a
JOIN submission_box sb ON a.id = sb.assessment_id
JOIN eportfolio_showcase_item item ON sb.id = item.submission_box_id
SET item.deleted_at = a.deleted_at
WHERE a.deleted_at IS NOT NULL AND (item.deleted_at IS NULL OR item.deleted_at > a.deleted_at);

-- Rubric Levels
UPDATE assessment a
JOIN submission_box sb ON a.id = sb.assessment_id
JOIN rubric_level rl ON sb.id = rl.box_id
SET rl.deleted_at = a.deleted_at
WHERE a.deleted_at IS NOT NULL AND (rl.deleted_at IS NULL OR rl.deleted_at > a.deleted_at);

-- Rubric descriptors
UPDATE rubric_level rl
JOIN rubric_descriptor rd ON rl.id = rd.level_id
SET rd.deleted_at = rl.deleted_at
WHERE rl.deleted_at IS NOT NULL AND (rd.deleted_at IS NULL OR rd.deleted_at > rl.deleted_at);

-- Student Submissions
UPDATE assessment a
JOIN submission_box sb ON a.id = sb.assessment_id
JOIN student_submission ss ON sb.id = ss.box_id
SET ss.deleted_at = a.deleted_at
WHERE a.deleted_at IS NOT NULL AND (ss.deleted_at IS NULL OR ss.deleted_at > a.deleted_at);

-- Student Submission Files
UPDATE student_submission ss
JOIN student_submission_file ssf ON ss.id = ssf.student_submission_id
SET ssf.deleted_at = ss.deleted_at
WHERE ss.deleted_at IS NOT NULL AND (ssf.deleted_at IS NULL OR ssf.deleted_at > ss.deleted_at);

-- Plagscan Docs
UPDATE student_submission_file ssf
JOIN plagscan_document pd ON ssf.id = pd.student_submission_file_id
SET pd.deleted_at = ssf.deleted_at
WHERE ssf.deleted_at IS NOT NULL AND (pd.deleted_at IS NULL OR pd.deleted_at > ssf.deleted_at);

-- Kami Files
UPDATE student_submission_file ssf
JOIN student_submission_file_kami ssfk ON ssf.id = ssfk.student_submission_file_id
SET ssfk.deleted_at = ssf.deleted_at
WHERE ssf.deleted_at IS NOT NULL AND (ssfk.deleted_at IS NULL OR ssfk.deleted_at > ssf.deleted_at);

-- Submission Returns
UPDATE assessment a
JOIN submission_box sb ON a.id = sb.assessment_id
JOIN submission_return sr ON sb.id = sr.box_id
SET sr.deleted_at = a.deleted_at
WHERE a.deleted_at IS NOT NULL AND (sr.deleted_at IS NULL OR sr.deleted_at > a.deleted_at);

-- Submission Return Files
UPDATE submission_return sr
JOIN submission_return_file srf ON sr.id = srf.submission_return_id
SET srf.deleted_at = sr.deleted_at
WHERE sr.deleted_at IS NOT NULL AND (srf.deleted_at IS NULL OR srf.deleted_at > sr.deleted_at);
