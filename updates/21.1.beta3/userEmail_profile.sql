ALTER TABLE user MODIFY alt_email VARCHAR(255) CHARACTER SET 'utf8mb4' COLLATE 'utf8mb4_unicode_ci' NULL DEFAULT NULL;
DROP TABLE user_profile;
DROP TABLE user_relation;
