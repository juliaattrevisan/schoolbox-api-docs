UPDATE submission_box SET mark_visibility=0 WHERE mark_visibility IS NULL;

-- Change requested in SA-14106.
-- Expected values are {0, 1, 2} as defined in library/Schoolbox/Assessment/Component/SubmissionBox.php.
ALTER TABLE submission_box CHANGE COLUMN mark_visibility mark_visibility tinyint(1) NOT NULL DEFAULT 0;
