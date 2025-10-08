-- Since feedback comments allow HTML now, we need to increase the maximum length of the comment field.
ALTER TABLE `submission_return` MODIFY COLUMN `comment` MEDIUMTEXT CHARSET 'utf8mb4' COLLATE 'utf8mb4_unicode_ci' DEFAULT NULL;
ALTER TABLE `assess_attempt` MODIFY COLUMN `comment` MEDIUMTEXT CHARSET 'utf8mb4' COLLATE 'utf8mb4_unicode_ci' DEFAULT NULL;
ALTER TABLE `lti_assessment_score` MODIFY COLUMN `comment` MEDIUMTEXT CHARSET 'utf8mb4' COLLATE 'utf8mb4_unicode_ci' DEFAULT NULL;

-- quiz_feedback comments relate to the per-item comments for each quiz, these are already HTML, however only TEXT
ALTER TABLE `quiz_feedback` MODIFY COLUMN `comment` MEDIUMTEXT CHARSET 'utf8mb4' COLLATE 'utf8mb4_unicode_ci' DEFAULT NULL;
