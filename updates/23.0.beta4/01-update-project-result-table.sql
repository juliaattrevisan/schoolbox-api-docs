-- update mark column to match mark in submission_return
ALTER TABLE `project_result`
    MODIFY COLUMN `mark`
        VARCHAR(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL;
