-- Since feedback comments allow HTML now, we need to increase the maximum length of the comment field.
ALTER TABLE `submission_return` MODIFY COLUMN `comment` LONGTEXT CHARSET 'utf8mb4' DEFAULT NULL;
ALTER TABLE `quiz_feedback` MODIFY COLUMN `comment` LONGTEXT CHARSET 'utf8mb4' DEFAULT NULL;
