alter table login_log modify username varchar(255) CHARACTER SET 'utf8mb4' COLLATE 'utf8mb4_unicode_ci' NULL DEFAULT NULL;
alter table user modify username varchar(255) CHARACTER SET 'utf8mb4' COLLATE 'utf8mb4_unicode_ci' NULL DEFAULT NULL;
alter table user_activity_log modify username varchar(255) CHARACTER SET 'utf8mb4' COLLATE 'utf8mb4_unicode_ci' NULL DEFAULT NULL;
alter table user_assume_log modify assumed varchar(255) CHARACTER SET 'utf8mb4' COLLATE 'utf8mb4_unicode_ci' null;

